<?php

namespace App\Service\Firebase;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Factory;
use Psr\Log\LoggerInterface;

final class FirebaseAdminFactory
{
    private ?Factory $factory = null;
    private bool $initialized = false;

    public function __construct(
        private readonly string $credentialsJson,
        private readonly string $credentialsPath,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        $this->boot();

        return $this->factory !== null;
    }

    public function auth(): ?Auth
    {
        $this->boot();

        return $this->factory?->createAuth();
    }

    public function firestore(): ?Firestore
    {
        $this->boot();

        return $this->factory?->createFirestore();
    }

    private function boot(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        try {
            $factory = new Factory();
            $credentials = $this->resolveCredentials();
            if ($credentials === null) {
                $this->logger->warning('[firebase] Admin SDK disabled: set FIREBASE_CREDENTIALS_JSON or FIREBASE_CREDENTIALS_PATH.');

                return;
            }

            if (is_string($credentials)) {
                $factory = $factory->withServiceAccount($credentials);
            } else {
                $factory = $factory->withServiceAccount($credentials);
            }

            $this->factory = $factory;
        } catch (\Throwable $e) {
            $this->logger->error('[firebase] Admin SDK init failed: ' . $e->getMessage());
            $this->factory = null;
        }
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function resolveCredentials(): array|string|null
    {
        $json = trim($this->credentialsJson);
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $path = trim($this->credentialsPath);
        if ($path !== '' && is_file($path)) {
            return $path;
        }

        return null;
    }
}
