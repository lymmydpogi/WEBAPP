<?php

namespace App\Service;

use App\Entity\ChatMessage;
use App\Entity\User;
use App\Exception\ChatMessageException;
use App\Repository\ChatMessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class ChatMessageService
{
    public const MSG_EMPTY = 'Message cannot be empty.';
    public const MSG_FORBIDDEN = 'You are not allowed to view this conversation.';
    public const MSG_NOT_FOUND = 'Conversation not found.';
    public const MSG_SEND_FAILED = 'Failed to send message. Please try again.';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChatMessageRepository $chatMessageRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function validateMessageContent(string $raw): string
    {
        $message = trim($raw);
        if ($message === '') {
            throw new ChatMessageException(self::MSG_EMPTY);
        }
        if (strlen($message) > ChatMessage::MAX_MESSAGE_LENGTH) {
            throw new ChatMessageException(
                sprintf('Message cannot exceed %d characters.', ChatMessage::MAX_MESSAGE_LENGTH)
            );
        }

        return $message;
    }

    public function sendUserMessage(User $client, string $rawMessage): ChatMessage
    {
        $message = $this->validateMessageContent($rawMessage);

        $chatMessage = new ChatMessage();
        $chatMessage->setUser($client);
        $chatMessage->setSenderType(ChatMessage::SENDER_USER);
        $chatMessage->setMessage($message);
        $chatMessage->setIsRead(false);

        $this->entityManager->persist($chatMessage);
        $this->entityManager->flush();

        return $chatMessage;
    }

    public function sendAdminReply(User $client, string $rawMessage): ChatMessage
    {
        $message = $this->validateMessageContent($rawMessage);

        $chatMessage = new ChatMessage();
        $chatMessage->setUser($client);
        $chatMessage->setSenderType(ChatMessage::SENDER_ADMIN);
        $chatMessage->setMessage($message);
        $chatMessage->setIsRead(false);

        $this->entityManager->persist($chatMessage);
        $this->entityManager->flush();

        return $chatMessage;
    }

    public function getClientConversation(User $client, bool $markAdminMessagesRead = true): array
    {
        if ($markAdminMessagesRead) {
            $this->chatMessageRepository->markAdminMessagesAsRead($client);
        }

        return $this->chatMessageRepository->findByUserChronological($client);
    }

    /**
     * @return array<int, array{user: User, latestMessage: ?ChatMessage, unreadCount: int}>
     */
    public function getAdminConversationSummaries(): array
    {
        return $this->chatMessageRepository->findAdminConversationSummaries();
    }

    public function getClientForAdmin(int $userId): User
    {
        $user = $this->userRepository->find($userId);
        if (!$user instanceof User) {
            throw new ChatMessageException(self::MSG_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        $hasMessages = $this->chatMessageRepository->findLatestMessageForUser($user) !== null;
        if (!$hasMessages) {
            throw new ChatMessageException(self::MSG_NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        return $user;
    }

    /**
     * @return ChatMessage[]
     */
    public function getAdminConversation(User $client, bool $markUserMessagesRead = true): array
    {
        if ($markUserMessagesRead) {
            $this->chatMessageRepository->markUserMessagesAsRead($client);
        }

        return $this->chatMessageRepository->findByUserChronological($client);
    }

    public static function serialize(ChatMessage $chatMessage): array
    {
        return [
            'id' => $chatMessage->getId(),
            'senderType' => $chatMessage->getSenderType(),
            'message' => $chatMessage->getMessage(),
            'isRead' => $chatMessage->isRead(),
            'createdAt' => $chatMessage->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $chatMessage->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    public static function displayName(User $user): string
    {
        $fullName = trim(sprintf('%s %s', $user->getFirstName() ?? '', $user->getLastName() ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        return (string) ($user->getName() ?: $user->getEmail() ?: 'Client');
    }
}
