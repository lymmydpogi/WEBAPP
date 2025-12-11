<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AnalyticsController extends AbstractController
{
    #[Route('/analytics', name: 'app_analytics_index')]
    public function index(
        Request $request,
        OrderRepository $orderRepository,
        ServicesRepository $servicesRepository,
        UserRepository $userRepository
    ): Response {
        // Check if generating a report
        $reportType = $request->query->get('reportType');
        $fromDate = $request->query->get('from');
        $toDate = $request->query->get('to');

        $reportsData = [];
        $tableHeaders = [];
        $reportTitle = '';

        // Generate report if requested
        if ($reportType) {
            $from = $fromDate ? \DateTime::createFromFormat('Y-m-d', $fromDate) : null;
            $to = $toDate ? \DateTime::createFromFormat('Y-m-d', $toDate) : null;

            match ($reportType) {
                'users' => $this->generateUsersReport($userRepository, $orderRepository, $from, $to, $reportsData, $tableHeaders, $reportTitle),
                'orders' => $this->generateOrdersReport($orderRepository, $from, $to, $reportsData, $tableHeaders, $reportTitle),
                'services' => $this->generateServicesReport($servicesRepository, $from, $to, $reportsData, $tableHeaders, $reportTitle),
                'revenue' => $this->generateRevenueReport($orderRepository, $from, $to, $reportsData, $tableHeaders, $reportTitle),
                default => null,
            };
        }

        return $this->renderAnalytics(
            $orderRepository, 
            $servicesRepository, 
            $userRepository,
            $reportType,
            $reportsData,
            $tableHeaders,
            $reportTitle
        );
    }

    private function generateUsersReport(UserRepository $userRepository, OrderRepository $orderRepository, ?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['User ID', 'Name', 'Email', 'Phone', 'Total Orders', 'Joined Date'];
        $reportTitle = 'Users Report';

        $qb = $userRepository->createQueryBuilder('u');

        if ($from) $qb->andWhere('u.createdAt >= :from')->setParameter('from', $from);
        if ($to) {
            $to->modify('+1 day');
            $qb->andWhere('u.createdAt < :to')->setParameter('to', $to);
        }

        $users = $qb->orderBy('u.createdAt', 'DESC')->getQuery()->getResult();

        foreach ($users as $user) {
            $ordersCount = $orderRepository->count(['user' => $user]);
            $reportsData[] = [
                $user->getId(),
                $user->getName(),
                $user->getEmail(),
                $user->getPhone() ?? 'N/A',
                $ordersCount,
                $user->getCreatedAt()?->format('Y-m-d') ?? 'N/A',
            ];
        }
    }

    private function generateOrdersReport(OrderRepository $orderRepository, ?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['Order ID', 'User', 'Service', 'Status', 'Amount', 'Date'];
        $reportTitle = 'Orders Report';

        $qb = $orderRepository->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->leftJoin('o.service', 's')
            ->addSelect('u', 's');

        if ($from) $qb->andWhere('o.orderDate >= :from')->setParameter('from', $from);
        if ($to) {
            $to->modify('+1 day');
            $qb->andWhere('o.orderDate < :to')->setParameter('to', $to);
        }

        $orders = $qb->orderBy('o.orderDate', 'DESC')->getQuery()->getResult();

        foreach ($orders as $order) {
            $reportsData[] = [
                $order->getId(),
                $order->getUser()?->getName() ?? 'N/A',
                $order->getService()?->getName() ?? 'N/A',
                ucfirst($order->getStatus() ?? 'pending'),
                '₱' . number_format($order->getTotalPrice() ?? 0, 2),
                $order->getOrderDate()?->format('Y-m-d') ?? 'N/A',
            ];
        }
    }

    private function generateServicesReport(ServicesRepository $servicesRepository, ?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['Service ID', 'Name', 'Description', 'Price', 'Status'];
        $reportTitle = 'Services Report';

        $qb = $servicesRepository->createQueryBuilder('s');
        $services = $qb->orderBy('s.id', 'DESC')->getQuery()->getResult();

        foreach ($services as $service) {
            $reportsData[] = [
                $service->getId(),
                $service->getName(),
                substr($service->getDescription() ?? '', 0, 50) . (strlen($service->getDescription() ?? '') > 50 ? '...' : ''),
                '₱' . number_format($service->getPrice() ?? 0, 2),
                $service->isActive() ? 'Active' : 'Inactive',
            ];
        }
    }

    private function generateRevenueReport(OrderRepository $orderRepository, ?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
{
    $tableHeaders = ['Date', 'Service', 'Orders Count', 'Total Revenue', 'Avg Amount'];
    $reportTitle = 'Revenue Report';

    $qb = $orderRepository->createQueryBuilder('o')
        ->leftJoin('o.service', 's')
        ->addSelect('s');

    if ($from) $qb->andWhere('o.orderDate >= :from')->setParameter('from', $from);
    if ($to) {
        $to->modify('+1 day');
        $qb->andWhere('o.orderDate < :to')->setParameter('to', $to);
    }

    $orders = $qb->orderBy('o.orderDate', 'DESC')->getQuery()->getResult();

    // Group orders manually by date and service
    $grouped = [];
    foreach ($orders as $order) {
        $date = $order->getOrderDate() ? $order->getOrderDate()->format('Y-m-d') : 'N/A';
        $serviceName = $order->getService()?->getName() ?? 'N/A';
        $key = $date . '|' . $serviceName;

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'date' => $date,
                'service' => $serviceName,
                'count' => 0,
                'total' => 0,
            ];
        }

        $grouped[$key]['count']++;
        $grouped[$key]['total'] += $order->getTotalPrice() ?? 0;
    }

    // Convert to report data
    foreach ($grouped as $row) {
        $avg = $row['count'] > 0 ? $row['total'] / $row['count'] : 0;
        $reportsData[] = [
            $row['date'],
            $row['service'],
            $row['count'],
            '₱' . number_format($row['total'], 2),
            '₱' . number_format($avg, 2),
        ];
    }
}

    private function renderAnalytics(
        OrderRepository $orderRepository,
        ServicesRepository $servicesRepository,
        UserRepository $userRepository,
        $reportType = '',
        array $reportsData = [],
        array $tableHeaders = [],
        string $reportTitle = ''
    ): Response {
        $now = new \DateTime();
        $firstDayMonth = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
        $lastDayMonth = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);

        // ────────── Basic Stats ──────────
        $monthlyRevenue = (float) $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.totalPrice)')
            ->where('o.orderDate BETWEEN :start AND :end')
            ->setParameter('start', $firstDayMonth)
            ->setParameter('end', $lastDayMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $pendingOrders = (int) $orderRepository->count(['status' => Order::STATUS_PENDING]);
        $totalClients = (int) $userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_CLIENT%')
            ->getQuery()
            ->getSingleScalarResult();
        $activeServices = (int) $servicesRepository->count([]);

        // ────────── Revenue Data (last 12 months) ──────────
        $revenueData = ['labels' => [], 'values' => []];
        for ($i = 11; $i >= 0; $i--) {
            $start = (new \DateTime("first day of -$i month"))->setTime(0, 0, 0);
            $end = (new \DateTime("last day of -$i month"))->setTime(23, 59, 59);

            $sum = (float) $orderRepository->createQueryBuilder('o')
                ->select('SUM(o.totalPrice)')
                ->where('o.orderDate BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult() ?? 0;

            $revenueData['labels'][] = $start->format('M Y');
            $revenueData['values'][] = $sum;
        }

        // ────────── Orders by Status ──────────
        $statuses = [
            Order::STATUS_PENDING,
            Order::STATUS_COMPLETED,
            Order::STATUS_CANCELED,
        ];
        $orderStatusData = ['labels' => $statuses, 'values' => []];

        $statusCounts = $orderRepository->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) as cnt')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $statusMap = [];
        foreach ($statusCounts as $row) {
            $statusMap[$row['status']] = (int) $row['cnt'];
        }
        foreach ($statuses as $status) {
            $orderStatusData['values'][] = $statusMap[$status] ?? 0;
        }

        // ────────── Top Services ──────────
        $topServices = $orderRepository->createQueryBuilder('o')
            ->select('s.name, COUNT(o.id) as cnt')
            ->leftJoin('o.service', 's')
            ->groupBy('s.id')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $topServicesData = ['labels' => [], 'values' => []];
        foreach ($topServices as $service) {
            $topServicesData['labels'][] = $service['name'] ?? 'N/A';
            $topServicesData['values'][] = (int) $service['cnt'];
        }

        // ────────── Client Growth ──────────
        $clientGrowthData = ['labels' => [], 'values' => []];
        for ($i = 11; $i >= 0; $i--) {
            $start = (new \DateTime("first day of -$i month"))->setTime(0, 0, 0);
            $end = (new \DateTime("last day of -$i month"))->setTime(23, 59, 59);

            $count = $userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->andWhere('u.roles LIKE :role')
                ->andWhere('u.createdAt BETWEEN :start AND :end')
                ->setParameter('role', '%ROLE_CLIENT%')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult();

            $clientGrowthData['labels'][] = $start->format('M Y');
            $clientGrowthData['values'][] = (int) $count;
        }

        // ────────── Payment Method Data ──────────
        $paymentMethods = ['Cash', 'Gcash', 'Credit Card'];
        $paymentMethodData = ['labels' => $paymentMethods, 'values' => []];

        foreach ($paymentMethods as $method) {
            $count = $orderRepository->count(['paymentMethod' => $method]);
            $paymentMethodData['values'][] = $count;
        }

        // ────────── Recent Activities (limit 5 total) ──────────
        $recentOrders = $orderRepository->createQueryBuilder('o')
            ->select('o, u')
            ->join('o.user', 'u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_CLIENT%')
            ->orderBy('o.orderDate', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $recentClients = $userRepository->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_CLIENT%')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $activities = [];

        foreach ($recentOrders as $order) {
            $activities[] = [
                'type' => 'order',
                'title' => 'New order received from ' . $order->getUser()->getName(),
                'time' => $order->getOrderDate(),
            ];
        }

        foreach ($recentClients as $client) {
            $activities[] = [
                'type' => 'client',
                'title' => 'New client registered: ' . $client->getName(),
                'time' => $client->getCreatedAt(),
            ];
        }

        // Sort by time descending and limit total to 5
        usort($activities, fn($a, $b) => $b['time'] <=> $a['time']);
        $activities = array_slice($activities, 0, 5);

        return $this->render('ADMIN/analytics/index.html.twig', [
            'monthlyRevenue' => $monthlyRevenue,
            'pendingOrders' => $pendingOrders,
            'totalUsers' => $totalClients,
            'activeServices' => $activeServices,
            'revenueData' => $revenueData,
            'monthlyRevenueChartData' => $revenueData,
            'orderStatusData' => $orderStatusData,
            'topServicesData' => $topServicesData,
            'clientGrowthData' => $clientGrowthData,
            'paymentMethodData' => $paymentMethodData,
            'activities' => $activities,
            // Report data
            'report_type' => $reportType,
            'table_headers' => $tableHeaders,
            'reports_data' => $reportsData,
            'report_title' => $reportTitle,
        ]);
    }
}