<?php

namespace App\Security;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_login_index';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_username', '');
        $request->getSession()->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('_password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // fallback
            return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
        }

        // ───────────── Log login activity ─────────────
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('LOGIN');
        $log->setActionDetails('User logged in');
        $log->setTargetEntity('User');
        $log->setTargetEntityId($user->getId());

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        // ───────────── Role-based redirect ─────────────
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            // Admin goes to /home
            return new RedirectResponse($this->urlGenerator->generate('app_home_index'));
        }

        if (in_array('ROLE_STAFF', $roles, true)) {
            // Staff goes to /services (or a staff dashboard route)
            return new RedirectResponse($this->urlGenerator->generate('app_services_index'));
        }

        if (in_array('ROLE_CLIENT', $roles, true)) {
            // Client dashboard
            return new RedirectResponse($this->urlGenerator->generate('app_client_dashboard'));
        }

        // Default fallback: back to login
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
