<?php

namespace App\EventListener;

use App\Entity\Services;
use App\Service\ServiceCatalogService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Services::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Services::class)]
final class ServicesEntityListener
{
    public function __construct(
        private readonly ServiceCatalogService $catalogService,
    ) {
    }

    public function prePersist(Services $service): void
    {
        if ($service->getStatus() === Services::STATUS_ACTIVE) {
            $service->setIsActive(true);
        } else {
            $service->setIsActive(false);
        }

        $this->catalogService->ensurePersistableFields($service);
    }

    public function preUpdate(Services $service): void
    {
        $this->prePersist($service);
    }
}
