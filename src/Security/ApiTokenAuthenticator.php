<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authentifie les requêtes de l'API mobile (ed_photo) via un jeton porteur :
 * en-tête « Authorization: Bearer <token> ». Le jeton est émis par la route de
 * connexion mobile (POST /api/mobile/login) et stocké sur l'utilisateur.
 *
 * La route de connexion elle-même ne porte pas de jeton : {@see supports()} la
 * laisse donc passer en anonyme (le contrôle d'accès la déclare PUBLIC_ACCESS).
 */
class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr((string) $request->headers->get('Authorization'), 7);

        if (trim($token) === '') {
            throw new CustomUserMessageAuthenticationException('Jeton d\'authentification manquant.');
        }

        return new SelfValidatingPassport(
            new UserBadge($token, function (string $token) {
                $user = $this->userRepository->findOneByApiToken($token);
                if ($user === null) {
                    throw new CustomUserMessageAuthenticationException('Jeton invalide ou expiré.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // laisse la requête suivre son cours vers le contrôleur
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => 'unauthorized', 'message' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
