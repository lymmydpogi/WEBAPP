<?php

namespace App\EventListener;

use App\Entity\Order;
use App\Service\Firestore\FirestoreOrderSyncService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: Order::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Order::class)]
#[AsEntityListener(event: Events::postRemove, entity: Order::class)]
final class OrderFirestoreListener
{
    public function __construct(
        private readonly FirestoreOrderSyncService $firestoreOrders,
    ) {
    }

    public function postPersist(Order $order, PostPersistEventArgs $event): void
    {
        try {
            $this->firestoreOrders->sync($order);
        } catch (\Throwable $e) {
            // Never fail order creation if optional Firestore sync is unavailable.
        }
    }

    public function postUpdate(Order $order, PostUpdateEventArgs $event): void
    {
        try {
            $this->firestoreOrders->sync($order);
        } catch (\Throwable $e) {
        }
    }

    public function postRemove(Order $order, PostRemoveEventArgs $event): void
    {
        $id = $order->getId();
        if ($id === null) {
            return;
        }

        try {
            $this->firestoreOrders->remove($id);
        } catch (\Throwable $e) {
        }
    }
}
