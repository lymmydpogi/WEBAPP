<?php

namespace App\Service\Admin;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class AdminLiveDataService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
        private readonly ServicesRepository $servicesRepository,
        private readonly UserRepository $userRepository,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
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
            $items[] = [
                'id' => $order->getId(),
                'clientName' => $user ? ($user->getName() ?: 'Unnamed User') : ($order->getClientName() ?: 'N/A'),
                'clientEmail' => $user ? (string) $user->getEmail() : ($order->getClientEmail() ?: 'N/A'),
                'serviceName' => $service ? $service->getName() : 'N/A',
                'status' => $order->getStatus(),
                'deliveryDate' => $order->getDeliveryDate()?->format('Y-m-d') ?? 'N/A',
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

        $this->em->clear();

        return $this->userRepository->findMobileLoginsSince($since);
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
