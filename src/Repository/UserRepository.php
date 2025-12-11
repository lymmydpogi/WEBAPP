<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    // ──────────────── Client Queries ────────────────

    private function createClientQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_CLIENT"%');
    }

    public function countAllClients(): int
    {
        return (int) $this->createClientQueryBuilder()
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveClients(): int
    {
        return (int) $this->createClientQueryBuilder()
            ->select('COUNT(u.id)')
            ->andWhere('u.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSuspendedClients(): int
    {
        return (int) $this->createClientQueryBuilder()
            ->select('COUNT(u.id)')
            ->andWhere('u.status = :status')
            ->setParameter('status', 'suspended')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all clients ordered by creation date (DESC)
     *
     * @param \DateTime|null $from Optional start date filter
     * @param \DateTime|null $to Optional end date filter
     *
     * @return User[]
     */
    public function findAllClientsOrderedByCreatedAt(?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $qb = $this->createClientQueryBuilder();

        if ($from) {
            $qb->andWhere('u.createdAt >= :from')->setParameter('from', $from);
        }

        if ($to) {
            $toEnd = clone $to;
            $toEnd->modify('+1 day');
            $qb->andWhere('u.createdAt < :to')->setParameter('to', $toEnd);
        }

        return $qb->orderBy('u.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    public function findClientById(int $id): ?User
    {
        return $this->createClientQueryBuilder()
            ->andWhere('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
