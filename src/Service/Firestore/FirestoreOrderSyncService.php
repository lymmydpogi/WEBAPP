<?php

namespace App\Service\Firestore;

use App\Entity\Order;
use App\Service\Firebase\FirebaseAdminFactory;
use Psr\Log\LoggerInterface;

final class FirestoreOrderSyncService
{
    private const COLLECTION = 'orders';

    public function __construct(
        private readonly FirebaseAdminFactory $firebase,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sync(Order $order): void
    {
        if ($order->getId() === null) {
            return;
        }

        $firestore = $this->firebase->firestore();
        if ($firestore === null) {
            return;
        }

        try {
            $user = $order->getUser();
            $service = $order->getService();
            $data = [
                'id' => $order->getId(),
                'userId' => $user?->getId(),
                'clientName' => $order->getClientName() ?? ($user?->getName() ?? 'Client'),
                'clientEmail' => $order->getClientEmail() ?? ($user?->getEmail() ?? ''),
                'serviceId' => $service?->getId(),
                'serviceName' => $service?->getName(),
                'status' => $order->getStatus(),
                'quantity' => $order->getQuantity(),
                'totalPrice' => $order->getTotalPrice() ?? 0.0,
                'notes' => $order->getNotes(),
                'orderDate' => $order->getOrderDate()?->format(\DateTimeInterface::ATOM),
                'deliveryDate' => $order->getDeliveryDate()?->format('Y-m-d'),
                'paymentMethod' => $order->getPaymentMethod(),
                'paymentStatus' => $order->getPaymentStatus(),
                'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];

            $firestore->database()
                ->collection(self::COLLECTION)
                ->document((string) $order->getId())
                ->set($data);
        } catch (\Throwable $e) {
            $this->logger->error('[firestore] Order sync failed for #' . $order->getId() . ': ' . $e->getMessage());
        }
    }

    public function remove(int $orderId): void
    {
        $firestore = $this->firebase->firestore();
        if ($firestore === null) {
            return;
        }

        try {
            $firestore->database()
                ->collection(self::COLLECTION)
                ->document((string) $orderId)
                ->delete();
        } catch (\Throwable $e) {
            $this->logger->error('[firestore] Order delete failed for #' . $orderId . ': ' . $e->getMessage());
        }
    }
}
