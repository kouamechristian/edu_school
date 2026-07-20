<?php

namespace App\Security;

use App\Repository\StudentRepository;
use App\Service\StudentAccountManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Authentification de l'espace Élève : l'élève se connecte avec son matricule
 * national (identifiant) et sa date de naissance (facteur de vérification).
 *
 * Aucun mot de passe classique : les deux informations sont vérifiées contre la
 * fiche élève. Le compte User (ROLE_ELEVE) est créé à la volée si nécessaire.
 */
class StudentAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'eleve_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly StudentRepository $studentRepository,
        private readonly StudentAccountManager $accountManager,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->attributes->get('_route') === self::LOGIN_ROUTE;
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $matricule = trim((string) $request->request->get('matricule', ''));
        $dateOfBirth = trim((string) $request->request->get('date_of_birth', ''));

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $matricule);

        return new SelfValidatingPassport(
            new UserBadge($matricule, function (string $identifier) use ($dateOfBirth) {
                $student = $this->studentRepository->findOneActiveByMatriculeNational($identifier);

                if (!$student || !$student->getDateOfBirth()) {
                    throw new CustomUserMessageAuthenticationException('Matricule ou date de naissance incorrect.');
                }

                // Vérification du 2e facteur : la date de naissance saisie doit
                // correspondre exactement à celle de la fiche élève (AAAA-MM-JJ).
                if ($dateOfBirth === '' || $student->getDateOfBirth()->format('Y-m-d') !== $dateOfBirth) {
                    throw new CustomUserMessageAuthenticationException('Matricule ou date de naissance incorrect.');
                }

                $user = $this->accountManager->ensureAccount($student);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Impossible d\'accéder à l\'espace élève. Contactez le secrétariat.');
                }

                return $user;
            }),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('eleve_dashboard'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
