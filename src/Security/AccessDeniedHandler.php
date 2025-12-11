<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private RouterInterface $router;
    private Security $security;

    public function __construct(RouterInterface $router, Security $security)
    {
        $this->router = $router;
        $this->security = $security;
    }

   public function handle(Request $request, AccessDeniedException $accessDeniedException): RedirectResponse
{
    // Safely add flash message using modern syntax
    if ($request->hasSession()) {
        $session = $request->getSession();
        $session->set('_security.access_denied_message', 'Access Denied: You do not have permission to view this page.');
    }

    $user = $this->security->getUser();

    if (!$user) {
        $redirectUrl = $this->router->generate('app_login_index', ['access_denied' => 1]);
    } elseif ($this->security->isGranted('ROLE_STAFF') && !$this->security->isGranted('ROLE_ADMIN')) {
        $redirectUrl = $this->router->generate('app_services_index', ['access_denied' => 1]);
    } else {
        $redirectUrl = $this->router->generate('app_home_index', ['access_denied' => 1]);
    }

    return new RedirectResponse($redirectUrl);
}
}