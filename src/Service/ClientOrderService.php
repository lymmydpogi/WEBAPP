<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Services;
use App\Entity\User;
use App\Exception\ClientOrderException;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client order lifecycle. Creation always INSERTs a new `order` row (never merge/update by service).
 */
class ClientOrderService
{
    public const MSG_NOT_OWNER = 'You are not allowed to modify this order.';
    public const MSG_NOT_EDITABLE = 'This order can no longer be edited because it is already being processed.';
    public const MSG_NOT_DELETABLE = 'This order can no longer be deleted because it is already being processed.';
    public const MSG_STATUS_CHANGED = 'This order was already updated by the admin and can no longer be modified.';
    public const MSG_SERVICE_INACTIVE = 'This service is currently inactive and cannot be ordered.';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private ServicesRepository $servicesRepository,
    ) {
    }

    /**
     * Create a brand-new order record (always INSERT, never merge with existing orders).
     *
     * - One order = one service (quantity is units for that single order line only).
     * - Re-ordering the same service creates another row with a new id.
     * - Does not look up prior orders by user_id/service_id.
     * - totalPrice is calculated from the current DB service price × quantity.
     */
    public function createFromServiceBrief(Services $service, User $client, string $projectBrief, int $quantity = 1): Order
    {
        $this->assertQuantityValid($quantity);
        $this->assertServiceAvailable($service);

        // New entity every time — duplicate service orders are allowed and stay separate.
        $order = new Order();
        $order->setService($service);
        $order->setUser($client);
        $order->setCreatedBy($client);
        $order->setNotes(trim($projectBrief));
        $order->setQuantity($quantity);
        $order->recalculateTotalFromService();
        $order->setStatus(Order::STATUS_PENDING);
        $order->setPaymentMethod(Order::PAYMENT_OTHER);
        $order->setPaymentStatus(Order::PAYMENT_STATUS_PENDING);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    public function getOrderForClient(User $client, int $orderId): Order
    {
        $order = $this->orderRepository->find($orderId);
        if (!$order instanceof Order) {
            throw new ClientOrderException('Order not found.', Response::HTTP_NOT_FOUND);
        }

        $this->assertClientOwnsOrder($client, $order);

        return $order;
    }

    /**
     * @param array{serviceId?: int, quantity?: int, notes?: string, projectBrief?: string} $payload
     */
    public function updatePendingOrder(User $client, int $orderId, array $payload): Order
    {
        $order = $this->getOrderForClient($client, $orderId);
        $this->assertOrderStillPending($order, self::MSG_STATUS_CHANGED);

        $serviceId = (int) ($payload['serviceId'] ?? $order->getService()?->getId() ?? 0);
        $quantity = (int) ($payload['quantity'] ?? $order->getQuantity());
        $notes = trim((string) ($payload['notes'] ?? $payload['projectBrief'] ?? $order->getNotes() ?? ''));

        if ($serviceId <= 0) {
            throw new ClientOrderException('Please select a service.', Response::HTTP_BAD_REQUEST);
        }
        $this->assertQuantityValid($quantity);
        if ($notes === '') {
            throw new ClientOrderException('Project brief is required.', Response::HTTP_BAD_REQUEST);
        }
        if (strlen($notes) < 20) {
            throw new ClientOrderException('Please provide at least 20 characters for your project brief.', Response::HTTP_BAD_REQUEST);
        }

        $service = $this->servicesRepository->find($serviceId);
        $this->assertServiceAvailable($service);

        $order->setService($service);
        $order->setQuantity($quantity);
        $order->setNotes($notes);
        $order->recalculateTotalFromService();

        $this->entityManager->flush();

        return $order;
    }

    public function cancelPendingOrder(User $client, int $orderId): Order
    {
        $order = $this->getOrderForClient($client, $orderId);
        $this->assertOrderStillPending($order, self::MSG_STATUS_CHANGED);

        $order->setStatus(Order::STATUS_CANCELLED);
        $this->entityManager->flush();

        return $order;
    }

    public function assertClientCanEdit(User $client, Order $order): void
    {
        $this->assertClientOwnsOrder($client, $order);
        if (!$order->canBeModifiedByClient()) {
            throw new ClientOrderException(self::MSG_NOT_EDITABLE);
        }
    }

    public function assertClientCanCancel(User $client, Order $order): void
    {
        $this->assertClientOwnsOrder($client, $order);
        if (!$order->canBeModifiedByClient()) {
            throw new ClientOrderException(self::MSG_NOT_DELETABLE);
        }
    }

    private function assertClientOwnsOrder(User $client, Order $order): void
    {
        $ownerId = $order->getUser()?->getId();
        $clientId = $client->getId();

        if ($ownerId === null || $clientId === null || $ownerId !== $clientId) {
            throw new ClientOrderException(self::MSG_NOT_OWNER, Response::HTTP_FORBIDDEN);
        }
    }

    private function assertOrderStillPending(Order $order, string $messageOnChanged): void
    {
        $this->entityManager->refresh($order);

        if (!$order->isPending()) {
            throw new ClientOrderException($messageOnChanged);
        }
    }

    private function assertQuantityValid(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new ClientOrderException('Quantity must be greater than zero.', Response::HTTP_BAD_REQUEST);
        }
    }

    private function assertServiceAvailable(?Services $service): void
    {
        if (!$service instanceof Services) {
            throw new ClientOrderException('Selected service is not available.', Response::HTTP_BAD_REQUEST);
        }

        if (!$service->isOrderable()) {
            throw new ClientOrderException(self::MSG_SERVICE_INACTIVE, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
