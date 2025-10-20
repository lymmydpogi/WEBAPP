<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class ReportsDataController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private OrderRepository $orderRepository,
        private ServicesRepository $serviceRepository,
    ) {}

    public function generateReport(string $reportType, ?\DateTime $from, ?\DateTime $to): Response
    {
        $reportsData = [];
        $tableHeaders = [];
        $reportTitle = '';

        match ($reportType) {
            'clients' => $this->generateClientsReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'orders' => $this->generateOrdersReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'services' => $this->generateServicesReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            'revenue' => $this->generateRevenueReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
            default => null,
        };

        return $this->render('reports/index.html.twig', [
            'reports_data' => $reportsData,
            'table_headers' => $tableHeaders,
            'report_title' => $reportTitle,
            'report_type' => $reportType,
        ]);
    }

    public function exportReport(string $format, string $type, ?string $fromDate, ?string $toDate): Response
    {
        $from = $fromDate ? \DateTime::createFromFormat('Y-m-d', $fromDate) : null;
        $to = $toDate ? \DateTime::createFromFormat('Y-m-d', $toDate) : null;

        $reportsData = [];
        $tableHeaders = [];
        $reportTitle = '';

        match ($type) {
            'clients' => $this->generateClientsReport($from, $to, $reportsData, $tableHeaders, $reportTitle),
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

    /** ---- REPORT GENERATORS ---- **/
    private function generateClientsReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['Client ID', 'Name', 'Email', 'Phone', 'Total Orders', 'Joined Date'];
        $reportTitle = 'Clients Report';

        $queryBuilder = $this->clientRepository->createQueryBuilder('c');

        if ($from) $queryBuilder->andWhere('c.createdAt >= :from')->setParameter('from', $from);
        if ($to) {
            $to->modify('+1 day');
            $queryBuilder->andWhere('c.createdAt < :to')->setParameter('to', $to);
        }

        $clients = $queryBuilder->orderBy('c.createdAt', 'DESC')->getQuery()->getResult();

        foreach ($clients as $client) {
            $ordersCount = $this->orderRepository->count(['client' => $client]);
            $reportsData[] = [
                $client->getId(),
                $client->getName(),
                $client->getEmail(),
                $client->getPhone() ?? 'N/A',
                $ordersCount,
                $client->getCreatedAt()?->format('Y-m-d') ?? 'N/A',
            ];
        }
    }

    private function generateOrdersReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['Order ID', 'Client', 'Service', 'Status', 'Amount', 'Date'];
        $reportTitle = 'Orders Report';

        $queryBuilder = $this->orderRepository->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')
            ->leftJoin('o.service', 's')
            ->addSelect('c', 's');

        if ($from) $queryBuilder->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        if ($to) {
            $to->modify('+1 day');
            $queryBuilder->andWhere('o.createdAt < :to')->setParameter('to', $to);
        }

        $orders = $queryBuilder->orderBy('o.createdAt', 'DESC')->getQuery()->getResult();

        foreach ($orders as $order) {
            $reportsData[] = [
                $order->getId(),
                $order->getClient()?->getName() ?? 'N/A',
                $order->getService()?->getName() ?? 'N/A',
                ucfirst($order->getStatus() ?? 'pending'),
                '₱' . number_format($order->getAmount() ?? 0, 2),
                $order->getCreatedAt()?->format('Y-m-d') ?? 'N/A',
            ];
        }
    }

    private function generateServicesReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['Service ID', 'Name', 'Description', 'Price', 'Status', 'Created Date'];
        $reportTitle = 'Services Report';

        $queryBuilder = $this->serviceRepository->createQueryBuilder('s');

        if ($from) $queryBuilder->andWhere('s.createdAt >= :from')->setParameter('from', $from);
        if ($to) {
            $to->modify('+1 day');
            $queryBuilder->andWhere('s.createdAt < :to')->setParameter('to', $to);
        }

        $services = $queryBuilder->orderBy('s.createdAt', 'DESC')->getQuery()->getResult();

        foreach ($services as $service) {
            $reportsData[] = [
                $service->getId(),
                $service->getName(),
                substr($service->getDescription() ?? '', 0, 50) . (strlen($service->getDescription() ?? '') > 50 ? '...' : ''),
                '₱' . number_format($service->getPrice() ?? 0, 2),
                $service->isActive() ? 'Active' : 'Inactive',
                $service->getCreatedAt()?->format('Y-m-d') ?? 'N/A',
            ];
        }
    }

    private function generateRevenueReport(?\DateTime $from, ?\DateTime $to, &$reportsData, &$tableHeaders, &$reportTitle): void
    {
        $tableHeaders = ['Date', 'Service', 'Orders Count', 'Total Revenue', 'Avg Amount'];
        $reportTitle = 'Revenue Report';

        $queryBuilder = $this->orderRepository->createQueryBuilder('o')
            ->leftJoin('o.service', 's')
            ->addSelect('s')
            ->select('DATE(o.createdAt) as orderDate', 's.name as serviceName', 'COUNT(o.id) as orderCount', 'SUM(o.amount) as totalRevenue', 'AVG(o.amount) as avgAmount')
            ->groupBy('orderDate', 's.name');

        if ($from) $queryBuilder->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        if ($to) {
            $to->modify('+1 day');
            $queryBuilder->andWhere('o.createdAt < :to')->setParameter('to', $to);
        }

        $results = $queryBuilder->orderBy('orderDate', 'DESC')->getQuery()->getResult();

        foreach ($results as $row) {
            $reportsData[] = [
                $row['orderDate'],
                $row['serviceName'] ?? 'N/A',
                $row['orderCount'] ?? 0,
                '₱' . number_format($row['totalRevenue'] ?? 0, 2),
                '₱' . number_format($row['avgAmount'] ?? 0, 2),
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
