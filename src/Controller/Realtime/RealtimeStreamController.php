<?php

namespace App\Controller\Realtime;

use App\Service\Realtime\RealtimeEventService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RealtimeStreamController extends AbstractController
{
    #[Route('/realtime/stream', name: 'realtime_stream', methods: ['GET'])]
    public function sse(Request $request, RealtimeEventService $realtime): Response
    {
        if ($this->getUser() === null) {
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $lastId = (int) ($request->query->get('lastEventId') ?? $request->headers->get('Last-Event-ID') ?? 0);

        $response = new StreamedResponse(function () use ($realtime, $lastId): void {
            @set_time_limit(30);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $cursor = $lastId;

            for ($i = 0; $i < 25; ++$i) {
                $events = $realtime->readAfterId($cursor, 100);

                foreach ($events as $event) {
                    $cursor = max($cursor, (int) $event['id']);

                    echo 'id: ' . $event['id'] . "\n";
                    echo 'event: app.action' . "\n";
                    echo 'data: ' . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                }

                // Keep-alive heartbeat so proxies do not close idle streams.
                echo ': heartbeat ' . time() . "\n\n";

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();

                usleep(1000000); // 1s
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
