<?php

namespace App\EventSubscriber\Realtime;

use App\Entity\User;
use App\Service\Realtime\RealtimeEventService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiRealtimeBroadcastSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RealtimeEventService $realtime,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $response = $event->getResponse();
        $status = $response->getStatusCode();
        if ($status >= 400) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        $user = $this->security->getUser();
        $actor = $this->serializeActor($user);
        $summary = $this->extractSummary($response->getContent());

        $this->realtime->publish('api.action', [
            'route' => $route,
            'path' => $path,
            'method' => $method,
            'status' => $status,
            'summary' => $summary,
            'actor' => $actor,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActor(mixed $user): array
    {
        if (!$user instanceof User) {
            return ['id' => null, 'email' => null, 'roles' => []];
        }

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];
    }

    private function extractSummary(?string $rawBody): ?string
    {
        if (!is_string($rawBody) || $rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return null;
        }

        $message = $decoded['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }
}
