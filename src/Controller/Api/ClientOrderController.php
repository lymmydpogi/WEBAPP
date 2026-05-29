<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\Services;
use App\Entity\User;
use App\Exception\ClientOrderException;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use App\Service\ClientOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ClientOrderController extends AbstractController
{
    public function __construct(
        private readonly ClientOrderService $clientOrderService,
    ) {
    }

    private function apiSuccess(string $message, array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ], $status);
    }

    private function apiError(string $message, int $status, array $errors = []): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    private function handleClientOrderException(ClientOrderException $e): JsonResponse
    {
        return $this->apiError($e->getMessage(), $e->getHttpStatus());
    }

    /**
     * Always creates a new order row. Never updates or merges an existing order for the same service.
     */
    #[Route('/api/client/orders/from-service', name: 'api_client_orders_from_service', methods: ['POST'])]
    public function createFromService(
        Request $request,
        ServicesRepository $servicesRepository,
    ): JsonResponse {
        $client = $this->requireClientUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $serviceId = (int) ($data['serviceId'] ?? 0);
        $serviceSlug = trim((string) ($data['serviceSlug'] ?? ''));
        $projectBrief = trim((string) ($data['projectBrief'] ?? ''));
        $quantity = (int) ($data['quantity'] ?? 1);

        $errors = [];
        if ($serviceId <= 0 && $serviceSlug === '') {
            $errors['serviceId'] = 'Please select a service.';
        }
        if ($projectBrief === '') {
            $errors['projectBrief'] = 'Please describe your project brief.';
        } elseif (strlen($projectBrief) < 20) {
            $errors['projectBrief'] = 'Please provide at least 20 characters so we understand your goals.';
        }
        if ($quantity <= 0) {
            $errors['quantity'] = 'Quantity must be greater than zero.';
        }

        if ($errors !== []) {
            return $this->apiError('Validation failed.', Response::HTTP_BAD_REQUEST, $errors);
        }

        $service = $serviceId > 0 ? $servicesRepository->find($serviceId) : null;
        if (!$service instanceof Services && $serviceSlug !== '') {
            $service = $servicesRepository->findOneBySlug($serviceSlug);
        }
        if (!$service instanceof Services) {
            return $this->apiError('Service not found.', Response::HTTP_NOT_FOUND, [
                'serviceId' => 'Invalid service.',
            ]);
        }

        if (!$service->isOrderable()) {
            return $this->apiError(
                \App\Service\ClientOrderService::MSG_SERVICE_INACTIVE,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['serviceId' => \App\Service\ClientOrderService::MSG_SERVICE_INACTIVE]
            );
        }

        try {
            $order = $this->clientOrderService->createFromServiceBrief($service, $client, $projectBrief, $quantity);

            $message = sprintf(
                'Your project brief for "%s" was submitted. We created your order and will follow up soon.',
                $service->getName()
            );

            return $this->apiSuccess($message, [
                'order' => $this->serializeOrder($order, $service),
                'created' => true,
                'orderId' => $order->getId(),
            ], Response::HTTP_CREATED);
        } catch (ClientOrderException $e) {
            return $this->handleClientOrderException($e);
        } catch (\Throwable $e) {
            return $this->apiError(
                'Could not create your order. Please try again.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }

    #[Route('/api/client/orders', name: 'api_client_orders_list', methods: ['GET'])]
    public function listMyOrders(OrderRepository $orderRepository): JsonResponse
    {
        $client = $this->requireClientUser();
        $orders = $orderRepository->findForClientNewestFirst($client);

        $items = [];
        foreach ($orders as $order) {
            $items[] = $this->serializeOrder($order, $order->getService());
        }

        return $this->apiSuccess('Orders loaded.', ['orders' => $items]);
    }

    #[Route('/api/client/orders/{id}', name: 'api_client_orders_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showOrder(int $id): JsonResponse
    {
        try {
            $client = $this->requireClientUser();
            $order = $this->clientOrderService->getOrderForClient($client, $id);

            return $this->apiSuccess('Order loaded.', [
                'order' => $this->serializeOrder($order, $order->getService()),
            ]);
        } catch (ClientOrderException $e) {
            return $this->handleClientOrderException($e);
        }
    }

    #[Route('/api/client/orders/{id}', name: 'api_client_orders_update', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function updateOrder(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $client = $this->requireClientUser();
            $order = $this->clientOrderService->getOrderForClient($client, $id);
            $this->clientOrderService->assertClientCanEdit($client, $order);

            $order = $this->clientOrderService->updatePendingOrder($client, $id, $data);

            return $this->apiSuccess('Order updated successfully.', [
                'order' => $this->serializeOrder($order, $order->getService()),
            ]);
        } catch (ClientOrderException $e) {
            return $this->handleClientOrderException($e);
        }
    }

    #[Route('/api/client/orders/{id}/cancel', name: 'api_client_orders_cancel', requirements: ['id' => '\d+'], methods: ['PATCH', 'POST', 'DELETE'])]
    public function cancelOrder(int $id): JsonResponse
    {
        try {
            $client = $this->requireClientUser();
            $order = $this->clientOrderService->getOrderForClient($client, $id);
            $this->clientOrderService->assertClientCanCancel($client, $order);

            $order = $this->clientOrderService->cancelPendingOrder($client, $id);

            return $this->apiSuccess('Order cancelled.', [
                'order' => $this->serializeOrder($order, $order->getService()),
            ]);
        } catch (ClientOrderException $e) {
            return $this->handleClientOrderException($e);
        }
    }

    private function serializeOrder(Order $order, ?Services $service): array
    {
        return [
            'id' => $order->getId(),
            'serviceId' => $service?->getId(),
            'serviceName' => $service?->getName(),
            'status' => $order->getStatus(),
            'quantity' => $order->getQuantity(),
            'notes' => $order->getNotes(),
            'totalPrice' => $order->getTotalPrice(),
            'orderDate' => $order->getOrderDate()?->format(\DateTimeInterface::ATOM),
            'paymentMethod' => $order->getPaymentMethod(),
            'paymentStatus' => $order->getPaymentStatus(),
            'canEdit' => $order->canBeModifiedByClient(),
            'canCancel' => $order->canBeModifiedByClient(),
        ];
    }

    private function requireClientUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isMobileAppUser()) {
            throw $this->createAccessDeniedException('Client account required.');
        }

        return $user;
    }
}
