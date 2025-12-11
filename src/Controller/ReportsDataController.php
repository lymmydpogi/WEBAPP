<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ReportsDataController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private OrderRepository $orderRepository,
        private ServicesRepository $serviceRepository,
    ) {}

    /**
     * Generate dynamic report for the dashboard
     */
    public function generateReport(string $reportType, ?\DateTime $from, ?\DateTime $to): Response
    {
        $reportsData = [];
        $tableHeaders = [];
        $reportTitle = '';

        match ($reportType) {
            'users' => $this->generateUsersReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'orders' => $this->generateOrdersReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'services' => $this->generateServicesReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'revenue' => $this->generateRevenueReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            default => null,
        };

        // Dashboard stats
        $totalClients = $this->userRepository->countAllClients();
        $activeClients = $this->userRepository->countActiveClients();
        $suspendedClients = $this->userRepository->countSuspendedClients();
        $totalOrders = $this->orderRepository->count();
        $totalRevenue = $this->orderRepository->getTotalRevenue() ?? 0;
        $activeServices = $this->serviceRepository->count(['is_active' => true]);


        return $this->render('ADMIN/reports/index.html.twig', [
            'reports_data' => $reportsData,
            'table_headers' => $tableHeaders,
            'report_title' => $reportTitle,
            'report_type' => $reportType,
            'totalClients' => $totalClients,
            'activeClients' => $activeClients,
            'suspendedClients' => $suspendedClients,
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'activeServices' => $activeServices,
        ]);
    }

    /**
     * Export report to PDF or Excel
     */
    public function exportReport(string $format, string $type, ?string $fromDate, ?string $toDate): Response
    {
        $from = $fromDate ? \DateTime::createFromFormat('Y-m-d', $fromDate) : null;
        $to = $toDate ? \DateTime::createFromFormat('Y-m-d', $toDate) : null;

        $reportsData = [];
        $tableHeaders = [];
        $reportTitle = '';

        match ($type) {
            'users' => $this->generateUsersReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'orders' => $this->generateOrdersReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'services' => $this->generateServicesReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'revenue' => $this->generateRevenueReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            default => null,
        };

        return match ($format) {
            'pdf' => $this->exportPdf($reportTitle, $tableHeaders, $reportsData),
            'excel' => $this->exportExcel($reportTitle, $tableHeaders, $reportsData),
            default => new Response('Invalid format', 400),
        };
    }

    public function generateReportForAnalytics(string $reportType, ?\DateTime $from, ?\DateTime $to): Response
    {
        $reportsData = [];
        $tableHeaders = [];
        $reportTitle = '';

        match ($reportType) {
            'users' => $this->generateUsersReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'orders' => $this->generateOrdersReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'services' => $this->generateServicesReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'revenue' => $this->generateRevenueReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            default => null,
        };

        // Use the AnalyticsController's index method but with report data
        return $this->forward('App\Controller\AnalyticsController::indexWithReport', [
            'reports_data' => $reportsData,
            'table_headers' => $tableHeaders,
            'report_title' => $reportTitle,
            'report_type' => $reportType,
        ]);
    }

    /** ---- REPORT GENERATORS ---- **/
    private function generateUsersReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['User ID', 'Name', 'Email', 'Phone', 'Total Orders', 'Joined Date'];
        $reportTitle = 'Users Report';

        $qb = $this->userRepository->createQueryBuilder('u');

        if ($from) $qb->andWhere('u.createdAt >= :from')->setParameter('from', $from);
        if ($to) {
            $to->modify('+1 day');
            $qb->andWhere('u.createdAt < :to')->setParameter('to', $to);
        }

        $users = $qb->orderBy('u.createdAt', 'DESC')->getQuery()->getResult();

        foreach ($users as $user) {
            $ordersCount = $this->orderRepository->count(['user' => $user]);
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

    private function generateOrdersReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
{
    $tableHeaders = ['Order ID', 'User', 'Service', 'Status', 'Amount', 'Date'];
    $reportTitle = 'Orders Report';

    $qb = $this->orderRepository->createQueryBuilder('o')
        ->leftJoin('o.user', 'u')
        ->leftJoin('o.service', 's')
        ->addSelect('u', 's');

    if ($from) $qb->andWhere('o.orderDate >= :from')->setParameter('from', $from); // Changed to orderDate
    if ($to) {
        $to->modify('+1 day');
        $qb->andWhere('o.orderDate < :to')->setParameter('to', $to); // Changed to orderDate
    }

    $orders = $qb->orderBy('o.orderDate', 'DESC')->getQuery()->getResult(); // Changed to orderDate

    foreach ($orders as $order) {
        $reportsData[] = [
            $order->getId(),
            $order->getUser()?->getName() ?? 'N/A',
            $order->getService()?->getName() ?? 'N/A',
            ucfirst($order->getStatus() ?? 'pending'),
            '₱' . number_format($order->getTotalPrice() ?? 0, 2),
            $order->getOrderDate()?->format('Y-m-d') ?? 'N/A', // Changed to getOrderDate()
        ];
    }

    }private function generateServicesReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
{
    $tableHeaders = ['Service ID', 'Name', 'Description', 'Price', 'Status'];
    $reportTitle = 'Services Report';

    $qb = $this->serviceRepository->createQueryBuilder('s');

    // Remove date filtering since Services doesn't have createdAt
    // if ($from) $qb->andWhere('s.createdAt >= :from')->setParameter('from', $from);
    // if ($to) {
    //     $to->modify('+1 day');
    //     $qb->andWhere('s.createdAt < :to')->setParameter('to', $to);
    // }

    $services = $qb->orderBy('s.id', 'DESC')->getQuery()->getResult(); // Changed to order by id

    foreach ($services as $service) {
        $reportsData[] = [
            $service->getId(),
            $service->getName(),
            substr($service->getDescription() ?? '', 0, 50) . (strlen($service->getDescription() ?? '') > 50 ? '...' : ''),
            '₱' . number_format($service->getPrice() ?? 0, 2),
            $service->isActive() ? 'Active' : 'Inactive',
            // Removed created date column since it doesn't exist
        ];
    }
}
    private function generateRevenueReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['Date', 'Service', 'Orders Count', 'Total Revenue', 'Avg Amount'];
        $reportTitle = 'Revenue Report';

        $qb = $this->orderRepository->createQueryBuilder('o')
            ->leftJoin('o.service', 's')
            ->addSelect('s')
            ->select('DATE(o.orderDate) as orderDate', 's.name as serviceName', 'COUNT(o.id) as orderCount', 'SUM(o.amount) as totalRevenue', 'AVG(o.amount) as avgAmount') // Changed to orderDate
            ->groupBy('orderDate', 's.name');

        if ($from) $qb->andWhere('o.orderDate >= :from')->setParameter('from', $from); // Changed to orderDate
        if ($to) {
            $to->modify('+1 day');
            $qb->andWhere('o.orderDate < :to')->setParameter('to', $to); // Changed to orderDate
        }

        $results = $qb->orderBy('orderDate', 'DESC')->getQuery()->getResult();

        foreach ($results as $row) {
            $reportsData[] = [
                $row['orderDate'],
                $row['serviceName'] ?? 'N/A',
                $row['orderCount'] ?? 0,
                '₱' . number_format($row['TotalPrice'] ?? 0, 2),
                '₱' . number_format($row['TotalPrice'] ?? 0, 2),
            ];
        }
    }
    /** ---- EXPORT HELPERS ---- **/
    private function exportPdf(string $title, array $headers, array $data): Response
    {
        $html = $this->generatePdfHtml($title, $headers, $data);
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="report_' . date('Y-m-d_H-i-s') . '.html"',
        ]);
    }

    private function generatePdfHtml(string $title, array $headers, array $data): string
    {
        $html = '<html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title></head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1><p>Generated on ' . date('Y-m-d H:i:s') . '</p><table border="1" cellspacing="0" cellpadding="5">';
        $html .= '<thead><tr>' . implode('', array_map(fn($h) => "<th>$h</th>", $headers)) . '</tr></thead><tbody>';
        foreach ($data as $row) {
            $html .= '<tr>' . implode('', array_map(fn($c) => "<td>$c</td>", $row)) . '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        return $html;
    }

    private function exportExcel(string $title, array $headers, array $data): Response
    {
        $csv = implode(',', $headers) . "\n";
        foreach ($data as $row) {
            $csv .= implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', $cell) . '"', $row)) . "\n";
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="report_' . date('Y-m-d_H-i-s') . '.csv"',
        ]);
    }
}
