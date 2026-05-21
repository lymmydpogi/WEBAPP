<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
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
        private UserRepository $userRepository,
    ) {}

    public function authenticate(Request $request): Passport
    {
        $email = mb_strtolower(trim((string) $request->request->get('_username', '')));
        $request->getSession()->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): User {
                $user = $this->userRepository->findOneByEmail($userIdentifier);
                if (!$user instanceof User) {
                    throw new UserNotFoundException(sprintf('User "%s" not found.', $userIdentifier));
                }

                return $user;
            }),
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

        // If the user clicked "Log in" from a client page, we redirect them back.
        $next = $request->request->get('next') ?? $request->query->get('next');
        if (in_array('ROLE_CLIENT', $user->getRoles(), true) && is_string($next) && $next !== '' && str_starts_with($next, '/client')) {
            return new RedirectResponse($next);
        }

        // Activity logging is handled by ActivitySubscriber (LoginSuccessEvent).

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
        // If login was initiated from the client side (modal posts with from_client=1),
        // send the user back to the client landing page instead of the admin login screen.
        if ($request->query->getBoolean('from_client') || $request->request->getBoolean('from_client')) {
            return $this->urlGenerator->generate('client_landing');
        }

        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
