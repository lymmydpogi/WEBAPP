<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class EntityActivityListener
{
    public function __construct(
        private Security $security
    ) {}

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->logActivity($args, 'CREATE');
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->logActivity($args, 'UPDATE');
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->logActivity($args, 'DELETE');
    }

    private function logActivity(LifecycleEventArgs $args, string $actionType): void
    {
        $entity = $args->getObject();

        // Skip ActivityLog entities to avoid infinite loops
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log allowed entities
        $allowedEntities = ['User', 'Order', 'Services'];
        $entityName = (new \ReflectionClass($entity))->getShortName();

        if (!in_array($entityName, $allowedEntities)) {
            return;
        }

        // Get the current logged-in user
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            return;
        }

        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

        $em = $args->getObjectManager();

        $log = new ActivityLog();
        $log->setUser($currentUser);
        $log->setAction("{$actionType}_{$entityName}");
        $log->setActionDetails("{$actionType} on {$entityName}" . ($entityId ? " (ID {$entityId})" : ""));
        $log->setTargetEntity($entityName);
        $log->setTargetEntityId($entityId);

        // Optional: Debug log
        $logFile = __DIR__ . '/../../var/log/activity_debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Logging {$actionType} on {$entityName}\n", FILE_APPEND);

        $em->persist($log);
        $em->flush();
    }
}
