<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Exception\MobileAccessDeniedException;
use App\Service\MobileAppAccessService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Enforces mobile-only roles on client API routes with clear error messages.
 */
final class MobileApiAccessSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private const MOBILE_PROTECTED_PATH_PATTERNS = [
        '#^/api/client/(orders|profile)#',
        '#^/api/messages#',
        '#^/api/me#',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly MobileAppAccessService $mobileAppAccess,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!$this->isMobileProtectedPath($path)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        try {
            $this->mobileAppAccess->assertCanUseMobileApp($user, true);
        } catch (MobileAccessDeniedException $e) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => [],
            ], $e->getHttpStatus()));
        }
    }

    private function isMobileProtectedPath(string $path): bool
    {
        foreach (self::MOBILE_PROTECTED_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
