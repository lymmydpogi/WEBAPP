<?php

namespace App\Service\Realtime;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RealtimeEventService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/realtime/events.log')]
        private readonly string $eventsFile,
        #[Autowire('%kernel.project_dir%/var/realtime/seq.txt')]
        private readonly string $sequenceFile,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $type, array $payload): int
    {
        $this->ensureStorage();

        $id = $this->nextId();
        $event = [
            'id' => $id,
            'type' => $type,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'payload' => $payload,
        ];

        $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            return $id;
        }

        $handle = fopen($this->eventsFile, 'ab');
        if ($handle === false) {
            return $id;
        }

        try {
            flock($handle, LOCK_EX);
            fwrite($handle, $line . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $id;
    }

    /**
     * @return array<int, array{id:int,type:string,at:string,payload:array<string,mixed>}>
     */
    public function readAfterId(int $lastId, int $limit = 120): array
    {
        if (!is_file($this->eventsFile)) {
            return [];
        }

        $events = [];
        $handle = fopen($this->eventsFile, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $decoded = json_decode(trim($line), true);
                if (!is_array($decoded) || !isset($decoded['id'], $decoded['type'], $decoded['at'], $decoded['payload'])) {
                    continue;
                }

                $id = (int) $decoded['id'];
                if ($id <= $lastId) {
                    continue;
                }

                $events[] = [
                    'id' => $id,
                    'type' => (string) $decoded['type'],
                    'at' => (string) $decoded['at'],
                    'payload' => is_array($decoded['payload']) ? $decoded['payload'] : [],
                ];

                if (count($events) >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return $events;
    }

    private function nextId(): int
    {
        $this->ensureStorage();

        $handle = fopen($this->sequenceFile, 'c+b');
        if ($handle === false) {
            return (int) floor(microtime(true) * 1000);
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $current = is_string($raw) ? (int) trim($raw) : 0;
            $next = $current + 1;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $next);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $next;
        } finally {
            fclose($handle);
        }
    }

    private function ensureStorage(): void
    {
        $dir = dirname($this->eventsFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_file($this->eventsFile)) {
            @touch($this->eventsFile);
        }

        if (!is_file($this->sequenceFile)) {
            @touch($this->sequenceFile);
        }
    }
}
