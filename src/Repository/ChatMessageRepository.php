<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * @return ChatMessage[]
     */
    public function findByUserChronological(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadFromUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.user = :user')
            ->andWhere('m.senderType = :senderType')
            ->andWhere('m.isRead = :read')
            ->setParameter('user', $user)
            ->setParameter('senderType', ChatMessage::SENDER_USER)
            ->setParameter('read', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markUserMessagesAsRead(User $user): void
    {
        $this->markMessagesAsRead($user, ChatMessage::SENDER_USER);
    }

    public function markAdminMessagesAsRead(User $user): void
    {
        $this->markMessagesAsRead($user, ChatMessage::SENDER_ADMIN);
    }

    private function markMessagesAsRead(User $user, string $senderType): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', ':read')
            ->andWhere('m.user = :user')
            ->andWhere('m.senderType = :senderType')
            ->andWhere('m.isRead = :unread')
            ->setParameter('read', true)
            ->setParameter('user', $user)
            ->setParameter('senderType', $senderType)
            ->setParameter('unread', false)
            ->getQuery()
            ->execute();
    }

    /**
     * @return User[]
     */
    public function findUsersWithMessages(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT u')
            ->from(User::class, 'u')
            ->innerJoin(ChatMessage::class, 'm', 'WITH', 'm.user = u')
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestMessageForUser(User $user): ?ChatMessage
    {
        return $this->findOneBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * @return array<int, array{
     *     user: User,
     *     latestMessage: ?ChatMessage,
     *     unreadCount: int
     * }>
     */
    public function findAdminConversationSummaries(): array
    {
        $summaries = [];
        foreach ($this->findUsersWithMessages() as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $summaries[] = [
                'user' => $user,
                'latestMessage' => $this->findLatestMessageForUser($user),
                'unreadCount' => $this->countUnreadFromUser($user),
            ];
        }

        usort($summaries, static function (array $a, array $b): int {
            $aTime = $a['latestMessage']?->getCreatedAt()?->getTimestamp() ?? 0;
            $bTime = $b['latestMessage']?->getCreatedAt()?->getTimestamp() ?? 0;

            return $bTime <=> $aTime;
        });

        return $summaries;
    }
}
