<?php

namespace App\Repository;

use App\Entity\Order;
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

}
