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
        $this->firestoreOrders->sync($order);
    }

    public function postUpdate(Order $order, PostUpdateEventArgs $event): void
    {
        $this->firestoreOrders->sync($order);
    }

    public function postRemove(Order $order, PostRemoveEventArgs $event): void
    {
        $id = $order->getId();
        if ($id !== null) {
            $this->firestoreOrders->remove($id);
        }
    }
}
