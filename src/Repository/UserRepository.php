<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
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

    // ──────────────── Client-like Queries ────────────────

    /**
     * Count all users with ROLE_CLIENT
     */
    public function countAllClients(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode("ROLE_CLIENT"))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active clients
     */
    public function countActiveClients(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.status = :status')
            ->setParameters([
                'role'   => json_encode("ROLE_CLIENT"),
                'status' => 'active',
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count suspended clients
     */
    public function countSuspendedClients(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->andWhere('u.status = :status')
            ->setParameters([
                'role'   => json_encode("ROLE_CLIENT"),
                'status' => 'suspended',
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all clients ordered by creation date
     *
     * @return User[]
     */
    public function findAllClientsOrderedByCreatedAt(): array
    {
        return $this->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode("ROLE_CLIENT"))
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
