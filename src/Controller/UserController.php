<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
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

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository, SchoolContextService $contextService): Response
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

        // Statistiques filtrées par établissement
        $stats = $userRepository->countByTypeInSchool($schoolId);

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

            // Créer un Employee si nécessaire
            if (in_array($user->getUserType(), ['enseignant', 'personnel', 'directeur'])) {
                $employee = $user->createEmployee();
                $entityManager->persist($employee);
            }

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

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash le nouveau mot de passe si fourni
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Créer un Employee si nécessaire
            if (in_array($user->getUserType(), ['enseignant', 'personnel', 'directeur']) && !$user->getEmployee()) {
                $employee = $user->createEmployee();
                $entityManager->persist($employee);
            }

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

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
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

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
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

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function resetPassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->isCsrfTokenValid('reset-password'.$user->getId(), $request->request->get('_token'))) {
            // Générer un mot de passe temporaire
            $tempPassword = bin2hex(random_bytes(8));
            $hashedPassword = $passwordHasher->hashPassword($user, $tempPassword);
            $user->setPassword($hashedPassword);
            
            $entityManager->flush();

            $this->addFlash('success', "Le mot de passe a été réinitialisé. Nouveau mot de passe temporaire : {$tempPassword}");
        }

        return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }
}

