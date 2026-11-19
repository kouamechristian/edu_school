<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Controller\Concern\RendersDocuments;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Security\RoleGrantPolicy;
use App\Security\Voter\UserManagementVoter;
use App\Service\ParentAccountGenerator;
use App\Service\SchoolContextService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_user_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    use HandlesEntityDeletion;
    use RendersDocuments;

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository, SchoolContextService $contextService, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();
        
        // Si pas d'établissement sélectionné, afficher un message
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les utilisateurs.');
            return $this->render('user/index.html.twig', [
                'users' => [],
                'stats' => [],
                'current_type' => null,
                'search_term' => null,
                'current_school' => null,
            ]);
        }

        $schoolId = $currentSchool->getId();

        // Filtres
        $type = $request->query->get('type');
        $search = $request->query->get('search');

        // Filtrer les utilisateurs selon l'établissement sélectionné
        if ($search) {
            $users = $userRepository->searchByNameOrEmailInSchool($search, $schoolId);
        } elseif ($type) {
            $users = $userRepository->findByTypeInSchool($type, $schoolId);
        } else {
            $users = $userRepository->findBySchool($schoolId);
        }

        // Statistiques filtrées par établissement, en excluant les comptes des
        // portails élève/parent (gérés dans leurs écrans dédiés).
        $stats = array_values(array_filter(
            $userRepository->countByTypeInSchool($schoolId),
            static fn (array $row) => !\in_array($row['userType'] ?? null, ['parent', 'eleve'], true),
        ));

        $users = $paginator->paginate($users, $request->query->getInt('page', 1), 50);

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'stats' => $stats,
            'current_type' => $type,
            'search_term' => $search,
            'current_school' => $currentSchool,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash le mot de passe
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }
            // Mot de passe défini par l'administrateur : changement obligatoire
            // à la première connexion.
            $user->setMustChangePassword(true);

            // L'Employee associé (et le Teacher éventuel) est créé automatiquement
            // par UserEmployeeSubscriber::postPersist selon le type d'utilisateur.
            // Ne pas le créer ici : cela provoquerait un doublon sur employee.user_id.
            $entityManager->persist($user);

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', "Impossible de créer l'utilisateur : une entrée existe déjà avec ces informations (adresse e-mail ou employé déjà associé). Vérifiez l'adresse e-mail et réessayez.");
                return $this->redirectToRoute('admin_user_new', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $this->addFlash('error', "Une erreur est survenue lors de la création de l'utilisateur. Veuillez réessayer ou contacter l'administrateur.");
                return $this->redirectToRoute('admin_user_new', [], Response::HTTP_SEE_OTHER);
            }

            $this->addFlash('success', 'L\'utilisateur a été créé avec succès.');

            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * Comptes Parents : liste des utilisateurs disposant du rôle ROLE_PARENT.
     */
    #[Route('/parents', name: 'parents', methods: ['GET'])]
    public function parents(Request $request, UserRepository $userRepository, SchoolContextService $contextService, \Knp\Component\Pager\PaginatorInterface $paginator, ParentAccountGenerator $generator): Response
    {
        $search = $request->query->get('search');
        $accounts = $this->findPortalAccounts('ROLE_PARENT', $search, $userRepository, $contextService);
        $accounts = $paginator->paginate($accounts, $request->query->getInt('page', 1), 50);

        $currentSchool = $contextService->getCurrentSchool();

        return $this->render('user/accounts.html.twig', [
            'accounts' => $accounts,
            'kind' => 'parent',
            'search_term' => $search,
            'eligible_count' => $currentSchool ? count($generator->findEligibleStudents($currentSchool)) : 0,
        ]);
    }

    /**
     * Génère les comptes parents des élèves de l'établissement courant dont le
     * champ « Autorité parentale » est renseigné, et les rattache à leurs enfants.
     *
     * Les mots de passe générés ne sont affichés qu'une seule fois, sur la page
     * de résultat (ils sont hachés en base).
     */
    #[Route('/parents/generate', name: 'parents_generate', methods: ['POST'])]
    public function generateParents(Request $request, SchoolContextService $contextService, ParentAccountGenerator $generator): Response
    {
        if (!$this->isCsrfTokenValid('generate_parent_accounts', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_user_parents');
        }

        $school = $contextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement avant de générer les comptes parents.');

            return $this->redirectToRoute('admin_user_parents');
        }

        // Hachage des mots de passe coûteux : on laisse le temps au lot de se terminer.
        set_time_limit(300);

        try {
            $result = $generator->generate($school);
        } catch (\Throwable) {
            $this->addFlash('error', 'Une erreur est survenue pendant la génération des comptes parents. Aucun compte n\'a été créé.');

            return $this->redirectToRoute('admin_user_parents');
        }

        // Rendu direct (pas de redirection) : les mots de passe en clair ne sont
        // jamais stockés, ni en base ni en session.
        $response = $this->render('user/parents_generated.html.twig', [
            'result' => $result,
            'school' => $school,
            'generated_at' => new \DateTimeImmutable(),
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * Fiche d'accès des parents (PDF) : matricule et nom de chaque élève, avec
     * l'identifiant de connexion de son parent et, pour les comptes jamais
     * activés, un nouveau mot de passe provisoire (cf. ParentAccountGenerator::buildAccessSheet).
     *
     * POST + jeton CSRF : l'action régénère des mots de passe.
     */
    #[Route('/parents/fiche-acces', name: 'parents_access_sheet', methods: ['POST'])]
    public function parentsAccessSheet(Request $request, SchoolContextService $contextService, ParentAccountGenerator $generator): Response
    {
        if (!$this->isCsrfTokenValid('parent_access_sheet', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('admin_user_parents');
        }

        $school = $contextService->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement avant d\'imprimer la fiche d\'accès.');

            return $this->redirectToRoute('admin_user_parents');
        }

        // Hachage des nouveaux mots de passe provisoires (~0,4 s chacun).
        set_time_limit(300);
        $rows = $generator->buildAccessSheet($school);

        if ($rows === []) {
            $this->addFlash('info', 'Aucun élève de cet établissement n\'est rattaché à un compte parent actif.');

            return $this->redirectToRoute('admin_user_parents');
        }

        $response = $this->renderPdf('user/parents_access_sheet_pdf.html.twig', [
            'rows' => $rows,
            'school' => $school,
            'school_year' => $contextService->getCurrentSchoolYear(),
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTimeImmutable(),
            // Nombre de parents ayant reçu un nouveau mot de passe provisoire.
            'reset_count' => count(array_unique(array_map(
                static fn (array $r) => $r['parent']->getId(),
                array_filter($rows, static fn (array $r) => $r['password'] !== null),
            ))),
        ], sprintf('acces-parents-%s.pdf', (new \DateTimeImmutable())->format('Y-m-d')), 'landscape');
        // Contient des mots de passe en clair : jamais en cache.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * Comptes Élèves : liste des utilisateurs disposant du rôle ROLE_ELEVE.
     */
    #[Route('/eleves', name: 'students', methods: ['GET'])]
    public function students(Request $request, UserRepository $userRepository, SchoolContextService $contextService, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search');
        $accounts = $this->findPortalAccounts('ROLE_ELEVE', $search, $userRepository, $contextService);
        $accounts = $paginator->paginate($accounts, $request->query->getInt('page', 1), 50);

        return $this->render('user/accounts.html.twig', [
            'accounts' => $accounts,
            'kind' => 'eleve',
            'search_term' => $search,
        ]);
    }

    /**
     * Comptes portail (parents/élèves) de l'établissement courant uniquement.
     */
    private function findPortalAccounts(string $role, ?string $search, UserRepository $userRepository, SchoolContextService $contextService): array
    {
        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les comptes.');

            return [];
        }

        return $userRepository->findAccountsByRole($role, $search, $currentSchool->getId());
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);

        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SchoolContextService $contextService
    ): Response {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);

        // Le formulaire ne propose que les établissements gérés par l'utilisateur
        // connecté : on mémorise les autres pour ne pas les retirer à la soumission.
        $available = $contextService->getAvailableSchools();
        $hiddenSchools = [];
        if (!RoleGrantPolicy::isRealSuperAdmin($this->getUser())) {
            foreach ($user->getSchools() as $school) {
                if (!$contextService->isSchoolAllowed($school, $available)) {
                    $hiddenSchools[] = $school;
                }
            }
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($hiddenSchools as $school) {
                $user->addSchool($school);
            }

            // Hash le nouveau mot de passe si fourni
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
                // Mot de passe imposé par un administrateur : l'utilisateur doit
                // le remplacer (sauf s'il modifie son propre compte).
                if ($user !== $this->getUser()) {
                    $user->setMustChangePassword(true);
                    $user->setApiToken(null);
                }
            }

            // L'Employee associé est synchronisé automatiquement par
            // UserEmployeeSubscriber::postUpdate (création si absent, sinon mise à jour).
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $e) {
                $this->addFlash('error', "Impossible d'enregistrer les modifications : une entrée existe déjà avec ces informations (adresse e-mail ou employé déjà associé).");
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $this->addFlash('error', "Une erreur est survenue lors de la modification de l'utilisateur. Veuillez réessayer ou contacter l'administrateur.");
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }

            $this->addFlash('success', 'L\'utilisateur a été modifié avec succès.');

            return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            // Empêcher la suppression de son propre compte
            if ($this->getUser() === $user) {
                $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
                return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
            }

            $this->deleteEntity(
                $entityManager,
                $user,
                'L\'utilisateur a été supprimé avec succès.',
                'Suppression impossible : cet utilisateur est encore lié à des données (paiements, transactions, etc.). Désactivez-le plutôt que de le supprimer.'
            );
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);

        if ($this->isCsrfTokenValid('toggle'.$user->getId(), $request->request->get('_token'))) {
            // Empêcher la désactivation de son propre compte
            if ($this->getUser() === $user) {
                $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
                return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
            }

            $user->setIsActive(!$user->isActive());
            $entityManager->flush();

            $status = $user->isActive() ? 'activé' : 'désactivé';
            $this->addFlash('success', "L'utilisateur a été {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reset-password', name: 'reset_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function resetPassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->isCsrfTokenValid('reset-password'.$user->getId(), $request->request->get('_token'))) {
            // Mot de passe par défaut, à changer obligatoirement à la prochaine connexion.
            $user->setPassword($passwordHasher->hashPassword($user, User::DEFAULT_PASSWORD));
            $user->setMustChangePassword(true);
            // Révoque le jeton de l'application mobile éventuellement en cours.
            $user->setApiToken(null);

            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Le mot de passe a été réinitialisé à « %s ». L\'utilisateur devra le changer à sa prochaine connexion.',
                User::DEFAULT_PASSWORD
            ));
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }
}

