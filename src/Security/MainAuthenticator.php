<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class MainAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    public const PARENT_LOGIN_ROUTE = 'parent_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * On intercepte les soumissions des DEUX pages de connexion : celle du
     * personnel (app_login) et celle, dédiée, de l'espace parent (parent_login).
     */
    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && in_array($request->attributes->get('_route'), [self::LOGIN_ROUTE, self::PARENT_LOGIN_ROUTE], true);
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('username', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Un parent « pur » (aucun rôle personnel) est dirigé vers son portail.
        $roles = $token->getRoleNames();
        $staffRoles = array_diff($roles, ['ROLE_PARENT', 'ROLE_USER']);
        if (in_array('ROLE_PARENT', $roles, true) && $staffRoles === []) {
            return new RedirectResponse($this->urlGenerator->generate('parent_dashboard'));
        }

        // Redirect to a default page after successful login
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    /**
     * Renvoie l'utilisateur sur la page de connexion d'origine : un échec depuis
     * l'espace parent ré-affiche la page parent (et non celle du personnel).
     */
    protected function getLoginUrl(Request $request): string
    {
        $route = $request->attributes->get('_route');

        if ($route === self::PARENT_LOGIN_ROUTE || str_starts_with($request->getPathInfo(), '/parent')) {
            return $this->urlGenerator->generate(self::PARENT_LOGIN_ROUTE);
        }

        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

