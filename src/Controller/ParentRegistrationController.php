<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\StudentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Auto-inscription d'un parent (accès public).
 *
 * Un parent qui n'a pas encore de compte le crée depuis la page de connexion en
 * fournissant son identité (nom, prénom, téléphone, mot de passe) et le
 * matricule national de son enfant — déjà connu de l'établissement (ancien élève).
 *
 * Le matricule national permet de relier automatiquement le parent à l'élève
 * (Student.parentUser) et à son établissement (User.schools). Le téléphone sert
 * d'identifiant de connexion.
 */
class ParentRegistrationController extends AbstractController
{
    #[Route('/parent/inscription', name: 'parent_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        StudentRepository $studentRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
    ): Response {
        // Un utilisateur déjà connecté n'a pas à s'inscrire.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Valeurs ressaisies en cas d'erreur (jamais le mot de passe).
        $old = [
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'matricule_national' => '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parent_register', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('parent_register');
            }

            $firstName = trim((string) $request->request->get('first_name'));
            $lastName = trim((string) $request->request->get('last_name'));
            $phone = trim((string) $request->request->get('phone'));
            $password = (string) $request->request->get('password');
            $matriculeNational = trim((string) $request->request->get('matricule_national'));

            $old = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'matricule_national' => $matriculeNational,
            ];

            $error = $this->validate($firstName, $lastName, $phone, $password, $matriculeNational);

            if ($error === null) {
                // L'enfant doit être un élève connu (ancien élève) identifié par son matricule national.
                $child = $studentRepository->findOneActiveByMatriculeNational($matriculeNational);

                if (!$child) {
                    $error = "Aucun élève actif ne correspond à ce matricule national. Vérifiez le matricule auprès du secrétariat.";
                } elseif (!$this->resolveSchool($child)) {
                    $error = "Cet élève n'est rattaché à aucun établissement. Contactez le secrétariat.";
                } elseif ($userRepository->findParentOfStudent($child) !== null) {
                    $error = "Cet élève est déjà rattaché à un compte parent. Si vous pensez qu'il s'agit d'une erreur, contactez le secrétariat.";
                } elseif ($userRepository->findOneBy(['username' => $phone]) !== null) {
                    $error = "Ce numéro de téléphone est déjà utilisé par un compte. Connectez-vous ou utilisez un autre numéro.";
                }

                if ($error === null) {
                    $parent = $this->createParent($firstName, $lastName, $phone, $password, $child, $passwordHasher);

                    $em->persist($parent);
                    $em->flush();

                    $this->addFlash('success', sprintf(
                        'Votre compte a été créé et relié à %s. Connectez-vous avec votre numéro de téléphone et votre mot de passe.',
                        $child->getFullName()
                    ));

                    // Pré-remplit l'identifiant sur la page de connexion parent.
                    return $this->redirectToRoute('parent_login', ['username' => $phone]);
                }
            }

            $this->addFlash('error', $error);
        }

        return $this->render('security/parent_register.html.twig', [
            'old' => $old,
        ]);
    }

    /**
     * Valide les champs du formulaire ; retourne le message d'erreur ou null.
     */
    private function validate(string $firstName, string $lastName, string $phone, string $password, string $matriculeNational): ?string
    {
        if ($firstName === '' || $lastName === '' || $phone === '' || $password === '' || $matriculeNational === '') {
            return 'Veuillez renseigner tous les champs.';
        }

        if (mb_strlen($password) < 6) {
            return 'Le mot de passe doit contenir au moins 6 caractères.';
        }

        // Le téléphone sert d'identifiant : exactement 10 chiffres.
        if (!preg_match('/^\d{10}$/', $phone)) {
            return 'Le numéro de téléphone doit contenir exactement 10 chiffres.';
        }

        return null;
    }

    /**
     * Construit le compte parent relié à l'enfant et à son établissement.
     */
    private function createParent(
        string $firstName,
        string $lastName,
        string $phone,
        string $password,
        \App\Entity\Student $child,
        UserPasswordHasherInterface $passwordHasher,
    ): User {
        $parent = new User();
        $parent->setUsername($phone);
        $parent->setEmail($this->generateTechnicalEmail($phone));
        $parent->setFirstName($firstName);
        $parent->setLastName($lastName);
        $parent->setPhone($phone);
        $parent->setUserType('parent');
        $parent->setRoles(['ROLE_PARENT']);
        $parent->setPassword($passwordHasher->hashPassword($parent, $password));

        // Liaison parent → enfant (pose Student.parentUser) et parent → établissement.
        $parent->addChild($child);
        if ($school = $this->resolveSchool($child)) {
            $parent->addSchool($school);
            $parent->setLastSchool($school);
        }

        return $parent;
    }

    /**
     * Établissement de l'élève, avec repli sur la classe puis le niveau.
     */
    private function resolveSchool(\App\Entity\Student $child): ?\App\Entity\School
    {
        return $child->getSchool()
            ?? $child->getClassroom()?->getSchool()
            ?? $child->getLevel()?->getSchool();
    }

    /**
     * Email technique unique dérivé du téléphone : la colonne email est NOT NULL
     * et unique, mais le parent s'identifie par son téléphone (username), pas par
     * un email réel.
     */
    private function generateTechnicalEmail(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: bin2hex(random_bytes(4));

        return sprintf('parent.%s@auto.edu-school.local', $digits);
    }
}
