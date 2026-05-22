<?php

namespace App\Service;

use App\Entity\Services;
use App\Entity\User;

/**
 * Defaults and publish rules for admin-created services (web + mobile catalog).
 */
final class ServiceCatalogService
{
    public function applyDefaultsForNew(Services $service, User $creator): void
    {
        $service->setCreatedBy($creator);
        $this->publish($service);

        if (!$service->getPricingModel()) {
            $service->setPricingModel('fixed');
        }
        if (!$service->getPricingUnit()) {
            $service->setPricingUnit($this->defaultUnitForModel((string) $service->getPricingModel()));
        }
        if (!$service->getDeliveryTime()) {
            $service->setDeliveryTime(7);
        }
        if (!$service->getCategory()) {
            $service->setCategory('General');
        }

        $this->ensurePersistableFields($service);
    }

    /** Active in admin + orderable in mobile app. */
    public function publish(Services $service): void
    {
        $service->setStatus(Services::STATUS_ACTIVE);
        $service->setIsActive(true);
        $this->ensurePersistableFields($service);
    }

    public function ensurePersistableFields(Services $service): void
    {
        if ($service->getToolsUsed() === null || trim($service->getToolsUsed()) === '') {
            $service->setToolsUsed('Standard tools');
        }
        if ($service->getRevisionLimit() === null || trim($service->getRevisionLimit()) === '') {
            $service->setRevisionLimit('2 revisions');
        }
    }

    public function defaultUnitForModel(string $pricingModel): string
    {
        return match ($pricingModel) {
            'hourly' => 'hour',
            'milestone' => 'milestone',
            default => 'project',
        };
    }
}
