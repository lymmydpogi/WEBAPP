<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(private UrlGeneratorInterface $router) {}

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if ($exception instanceof AccessDeniedException) {
            if (str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
                $event->setResponse(new JsonResponse([
                    'success' => false,
                    'message' => 'Access denied.',
                    'data' => null,
                    'errors' => [],
                ], Response::HTTP_FORBIDDEN));

                return;
            }

            $url = $this->router->generate('app_login_index', ['access_denied' => 1]);
            $event->setResponse(new RedirectResponse($url));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }
}
