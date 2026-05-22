<?php

namespace App\Repository;

use App\Entity\Services;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ServicesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Services::class);
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.status = :status')
            ->setParameter('status', Services::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Services[]
     */
    public function findActiveOrderedByName(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', Services::STATUS_ACTIVE)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All services for client catalog (active + inactive).
     *
     * @return Services[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?Services
    {
        $normalizedSlug = self::slugify($slug);

        foreach ($this->findAllOrderedByName() as $service) {
            if (self::slugify((string) $service->getName()) === $normalizedSlug) {
                return $service;
            }
        }

        return null;
    }

    /** @deprecated Use findOneBySlug() */
    public function findOneActiveBySlug(string $slug): ?Services
    {
        $service = $this->findOneBySlug($slug);

        return $service?->isOrderable() ? $service : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeForClient(Services $service): array
    {
        $orderable = $service->isOrderable();

        return [
            'id' => $service->getId(),
            'name' => $service->getName(),
            'slug' => self::slugify((string) $service->getName()),
            'description' => $service->getDescription(),
            'price' => $service->getPrice(),
            'status' => $service->getStatus(),
            'statusLabel' => $service->getStatusLabel(),
            'isOrderable' => $orderable,
            'is_active' => $orderable,
            'visibleInApp' => $orderable,
            'category' => $service->getCategory(),
            'pricingModel' => $service->getPricingModel(),
            'pricingUnit' => $service->getPricingUnit(),
            'deliveryTime' => $service->getDeliveryTime(),
        ];
    }

    public static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'service';
    }
}
