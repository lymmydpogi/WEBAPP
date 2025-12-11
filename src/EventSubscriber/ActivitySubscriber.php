<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class ActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) return;

        // Prevent duplicate login entries within 5 seconds
        $recentLog = $this->entityManager->getRepository(ActivityLog::class)
            ->findOneBy(['user' => $user, 'action' => 'LOGIN'], ['createdAt' => 'DESC']);

        if ($recentLog) {
            $diff = (new \DateTimeImmutable())->getTimestamp() - $recentLog->getCreatedAt()->getTimestamp();
            if ($diff < 5) {
                return;
            }
        }

        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('LOGIN');
        $log->setActionDetails('User logged in');
        $log->setTargetEntity('User');
        $log->setTargetEntityId($user->getId());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) return;

        $user = $token->getUser();
        if (!$user instanceof User) return;

        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction('LOGOUT');
        $log->setActionDetails('User logged out');
        $log->setTargetEntity('User');
        $log->setTargetEntityId($user->getId());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
