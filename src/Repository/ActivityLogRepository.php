<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function deleteByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->count(['user' => $user]);
    }

    // Add custom queries if needed later
    // Example:
    // public function findRecentLogs(int $limit = 50)
    // {
    //     return $this->createQueryBuilder('a')
    //         ->orderBy('a.createdAt', 'DESC')
    //         ->setMaxResults($limit)
    //         ->getQuery()
    //         ->getResult()
    //     ;
    // }
}
