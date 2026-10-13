<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Authentification des applications mobiles (parent, enseignant) par jeton porteur :
 * contrôle des identifiants, émission du jeton et changement de mot de passe,
 * avec les mêmes règles que l'interface web.
 *
 * Les refus sont levés en {@see MobileAuthException} (code + message + statut HTTP),
 * que les contrôleurs API convertissent en réponse JSON.
 */
class MobileAuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MaintenanceMode $maintenance,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    /**
     * Contrôles communs à la connexion et au premier login : limitation des
     * tentatives, identifiants, compte actif, rôle requis, maintenance.
     *
     * @throws MobileAuthException
     */
    public function checkCredentials(
        Request $request,
        string $username,
        string $password,
        string $requiredRole,
        string $forbiddenMessage,
        RateLimiterFactory $limiterFactory,
    ): User {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new MobileAuthException('missing_credentials', 'Identifiant et mot de passe requis.', Response::HTTP_BAD_REQUEST);
        }

        $limiter = $limiterFactory->create($request->getClientIp() . '|' . mb_strtolower($username));
        if (!$limiter->consume()->isAccepted()) {
            throw new MobileAuthException('too_many_attempts', 'Trop de tentatives. Réessayez dans quelques minutes.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = $this->userRepository->loadUserByIdentifier($username);
        if (!$user instanceof User || !$user->isActive() || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new MobileAuthException('invalid_credentials', 'Identifiants invalides.', Response::HTTP_UNAUTHORIZED);
        }

        // Rôles hérités compris (ex. un directeur est aussi enseignant, cf. role_hierarchy).
        if (!in_array($requiredRole, $this->roleHierarchy->getReachableRoleNames($user->getRoles()), true)) {
            throw new MobileAuthException('forbidden', $forbiddenMessage, Response::HTTP_FORBIDDEN);
        }

        if ($this->maintenance->isEnabled() && !$this->maintenance->canBypass($user)) {
            throw new MobileAuthException('maintenance', "L'application est en maintenance. Veuillez réessayer plus tard.", Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $limiter->reset();

        return $user;
    }

    /**
     * Émet un nouveau jeton porteur (remplace le précédent) et le persiste.
     */
    public function issueToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setApiToken($token);
        $user->setLastLogin(new \DateTime());
        $this->em->flush();

        return $token;
    }

    /**
     * Applique un nouveau mot de passe avec les mêmes règles que le formulaire web
     * (ChangePasswordType + ChangePasswordController). Ne flush pas.
     *
     * @throws MobileAuthException
     */
    public function applyNewPassword(User $user, string $plainPassword): void
    {
        $violations = $this->validator->validate($plainPassword, [
            new Assert\NotBlank(message: 'Veuillez saisir un mot de passe.'),
            new Assert\Length(
                min: 6,
                max: 4096,
                minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
            ),
        ]);
        if (count($violations) > 0) {
            throw new MobileAuthException('invalid_password', (string) $violations[0]->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($this->passwordHasher->isPasswordValid($user, $plainPassword)) {
            throw new MobileAuthException('same_password', 'Le nouveau mot de passe doit être différent de l\'actuel.', Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setMustChangePassword(false);
    }

    /**
     * Changement à la demande (profil) : vérifie le mot de passe actuel puis applique.
     *
     * @throws MobileAuthException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new MobileAuthException('invalid_current_password', 'Le mot de passe actuel est incorrect.', Response::HTTP_BAD_REQUEST);
        }

        $this->applyNewPassword($user, $newPassword);
        $this->em->flush();
    }
}
