<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 *
 * Client order creation must use ClientOrderService (always persist a new Order).
 * This repository must not implement "find existing pending order by user+service" for merges.
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Calculate total revenue from all orders.
     */
    public function getTotalRevenue(): float
    {
        return (float) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalPrice), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Order>
     */
    public function findForClientNewestFirst(User $client): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :client')
            ->setParameter('client', $client)
            ->orderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Orders placed after admin started watching (or newer than last seen id).
     *
     * @return list<Order>
     */
    public function findNewOrdersForAdminAlert(\DateTimeImmutable $since, int $maxOrderId): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')->addSelect('u')
            ->leftJoin('o.service', 's')->addSelect('s')
            ->setParameter('since', $since);

        if ($maxOrderId > 0) {
            $qb->andWhere('o.orderDate >= :since OR o.id > :maxOrderId')
                ->setParameter('maxOrderId', $maxOrderId);
        } else {
            $qb->andWhere('o.orderDate >= :since');
        }

        return $qb->orderBy('o.id', 'ASC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();
    }

}
