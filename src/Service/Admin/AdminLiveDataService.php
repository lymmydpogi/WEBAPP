<?php

namespace App\Service\Admin;

use App\Entity\ChatMessage;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\ChatMessageRepository;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class AdminLiveDataService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ServicesRepository $servicesRepository,
        private readonly UserRepository $userRepository,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{orders: list<array<string, mixed>>, revision: string, count: int}
     */
    public function getOrdersSnapshot(): array
    {
        $this->em->clear();

        $orders = $this->orderRepository->findBy([], ['id' => 'DESC']);
        $items = [];

        foreach ($orders as $order) {
            $user = $order->getUser();
            $service = $order->getService();
            $notes = trim((string) ($order->getNotes() ?? ''));
            $notesPreview = $notes;
            if (mb_strlen($notesPreview) > 120) {
                $notesPreview = mb_substr($notesPreview, 0, 117) . '...';
            }

            $items[] = [
                'id' => $order->getId(),
                'clientName' => $user ? ($user->getName() ?: 'Unnamed User') : ($order->getClientName() ?: 'N/A'),
                'clientEmail' => $user ? (string) $user->getEmail() : ($order->getClientEmail() ?: 'N/A'),
                'serviceName' => $service ? $service->getName() : 'N/A',
                'status' => $order->getStatus(),
                'deliveryDate' => $order->getDeliveryDate()?->format('Y-m-d') ?? 'N/A',
                'totalPrice' => (float) ($order->getTotalPrice() ?? 0),
                'quantity' => (int) $order->getQuantity(),
                'notesPreview' => $notesPreview,
                'actionsHtml' => $this->twig->render('ADMIN/_TABLES/order/_poll_actions.html.twig', [
                    'order' => $order,
                ]),
            ];
        }

        $maxOrderId = (int) ($items[0]['id'] ?? 0);

        return [
            'orders' => $items,
            'revision' => $this->buildOrdersRevision($items, $maxOrderId),
            'count' => count($items),
            'maxOrderId' => $maxOrderId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboardSnapshot(): array
    {
        $this->em->clear();

        $currentDate = new \DateTime();
        $startOfMonth = (clone $currentDate)->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = (clone $currentDate)->modify('last day of this month')->setTime(23, 59, 59);

        $pendingOrders = (int) $this->orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :status')
            ->setParameter('status', Order::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $monthlyRevenue = (float) ($this->orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalPrice)')
            ->where('o.orderDate BETWEEN :start AND :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $data = [
            'activeServices' => $this->servicesRepository->count([]),
            'pendingOrders' => $pendingOrders,
            'totalUsers' => $this->userRepository->countAllClients(),
            'monthlyRevenue' => $monthlyRevenue,
            'totalRevenue' => $this->orderRepository->getTotalRevenue(),
            'totalOrders' => (int) $this->orderRepository->count([]),
        ];

        $data['revision'] = hash('xxh128', json_encode($data, JSON_THROW_ON_ERROR));

        return $data;
    }

    /**
     * @return list<array{userId: int, email: string, name: string, loggedAt: string}>
     */
    public function getMobileLoginsSince(?\DateTimeImmutable $since): array
    {
        if ($since === null) {
            return [];
        }

        try {
            $this->em->clear();

            return $this->userRepository->findMobileLoginsSince($since);
        } catch (\Throwable $e) {
            $this->logger->warning('Admin live: mobile login poll skipped.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<array{
     *     messageId: int,
     *     userId: int,
     *     clientName: string,
     *     preview: string,
     *     createdAt: string
     * }>
     */
    public function getClientMessagesSince(?\DateTimeImmutable $since): array
    {
        if ($since === null) {
            return [];
        }

        try {
            $this->em->clear();
        } catch (\Throwable $e) {
            $this->logger->warning('Admin live: message poll skipped.', ['error' => $e->getMessage()]);

            return [];
        }

        try {
            $messages = $this->chatMessageRepository->findUserMessagesSince($since);
        } catch (\Throwable $e) {
            $this->logger->warning('Admin live: message query failed.', ['error' => $e->getMessage()]);

            return [];
        }

        $items = [];

        foreach ($messages as $message) {
            if (!$message instanceof ChatMessage) {
                continue;
            }

            $user = $message->getUser();
            if ($user === null) {
                continue;
            }

            $body = trim($message->getMessage());
            $preview = $body;
            if (mb_strlen($preview) > 100) {
                $preview = mb_substr($preview, 0, 97) . '...';
            }

            $createdAt = $message->getCreatedAt();
            if ($createdAt === null) {
                continue;
            }

            $items[] = [
                'messageId' => (int) $message->getId(),
                'userId' => (int) $user->getId(),
                'clientName' => $user->getName() ?: (string) $user->getEmail(),
                'preview' => $preview,
                'createdAt' => $createdAt->format(\DateTimeInterface::ATOM),
            ];
        }

        return $items;
    }

    /**
     * Unified customer-activity feed for admin banner alerts.
     *
     * @return list<array{id: string, type: string, title: string, message: string, href: string, at: string}>
     */
    public function buildActivitiesSince(\DateTimeImmutable $since, int $maxOrderId = 0): array
    {
        $since = $since->modify('-3 seconds');
        $activities = [];

        try {
            $this->em->clear();
            foreach ($this->orderRepository->findNewOrdersForAdminAlert($since, $maxOrderId) as $order) {
                if (!$order instanceof Order) {
                    continue;
                }

                $user = $order->getUser();
                $service = $order->getService();
                $client = $user ? ($user->getName() ?: 'A client') : ($order->getClientName() ?: 'A client');
                $serviceName = $service ? $service->getName() : 'a service';
                $orderDate = $order->getOrderDate() ?? new \DateTimeImmutable();

                $activities[] = [
                    'id' => 'order-' . $order->getId(),
                    'type' => 'order',
                    'title' => 'New customer order',
                    'message' => '#' . $order->getId() . ' — ' . $client . ' ordered ' . $serviceName,
                    'href' => $this->urlGenerator->generate('app_order_show', ['id' => $order->getId()]),
                    'at' => $orderDate->format(\DateTimeInterface::ATOM),
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error('Admin live: order activities failed.', ['error' => $e->getMessage()]);
        }

        foreach ($this->getClientMessagesSince($since) as $msg) {
            $userId = (int) ($msg['userId'] ?? 0);
            $activities[] = [
                'id' => 'message-' . ($msg['messageId'] ?? 0),
                'type' => 'message',
                'title' => 'New customer message',
                'message' => ($msg['clientName'] ?? 'A client') . ': “' . ($msg['preview'] ?? '') . '”',
                'href' => $userId > 0
                    ? $this->urlGenerator->generate('admin_messages_show', ['userId' => $userId])
                    : $this->urlGenerator->generate('admin_messages_index'),
                'at' => (string) ($msg['createdAt'] ?? ''),
            ];
        }

        foreach ($this->getMobileLoginsSince($since) as $login) {
            $activities[] = [
                'id' => 'login-' . ($login['userId'] ?? 0) . '-' . ($login['loggedAt'] ?? ''),
                'type' => 'login',
                'title' => 'Customer signed in',
                'message' => ($login['name'] ?? 'A client') . ' logged in on the mobile app.',
                'href' => $this->urlGenerator->generate('app_user_index'),
                'at' => (string) ($login['loggedAt'] ?? ''),
            ];
        }

        usort($activities, static fn (array $a, array $b): int => strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? '')));

        return $activities;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function buildOrdersRevision(array $items, int $maxOrderId): string
    {
        $parts = [count($items) . '|max:' . $maxOrderId];
        foreach ($items as $item) {
            $parts[] = implode('|', [
                (string) ($item['id'] ?? ''),
                (string) ($item['status'] ?? ''),
                (string) ($item['deliveryDate'] ?? ''),
                (string) ($item['clientName'] ?? ''),
                (string) ($item['serviceName'] ?? ''),
                (string) ($item['totalPrice'] ?? ''),
                (string) ($item['quantity'] ?? ''),
                (string) ($item['notesPreview'] ?? ''),
            ]);
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /**
     * @return array{orders: list<array<string, mixed>>, revision: string, count: int}
     */
    public function getClientOrdersSnapshot(User $client): array
    {
        $this->em->clear();

        $orders = $this->orderRepository->findBy(['user' => $client], ['orderDate' => 'DESC']);
        $items = [];

        foreach ($orders as $order) {
            $service = $order->getService();
            $items[] = [
                'id' => $order->getId(),
                'serviceName' => $service ? $service->getName() : 'Service',
                'status' => $order->getStatus(),
                'orderDate' => $order->getOrderDate()?->format('M d, Y') ?? '',
                'totalPrice' => (float) ($order->getTotalPrice() ?? 0),
                'canEdit' => $order->getStatus() === Order::STATUS_PENDING,
                'showUrl' => $this->urlGenerator->generate('client_order_show', ['id' => $order->getId()]),
                'editUrl' => $this->urlGenerator->generate('client_order_edit', ['id' => $order->getId()]),
            ];
        }

        $maxOrderId = (int) ($items[0]['id'] ?? 0);

        return [
            'orders' => $items,
            'revision' => $this->buildOrdersRevision($items, $maxOrderId),
            'count' => count($items),
            'maxOrderId' => $maxOrderId,
        ];
    }
}
