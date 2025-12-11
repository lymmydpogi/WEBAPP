<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/reports')]
class ReportsController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private OrderRepository $orderRepository,
        private ServicesRepository $serviceRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Main reports dashboard
     */
    #[Route('', name: 'app_reports_index', methods: ['GET'])]
    public function index(): Response
    {
        // Dashboard summary
        $totalClients = $this->userRepository->countAllClients();
        $activeClients = $this->userRepository->countActiveClients();
        $suspendedClients = $this->userRepository->countSuspendedClients();
        $totalOrders = $this->orderRepository->count();
        $totalRevenue = $this->orderRepository->getTotalRevenue() ?? 0;
        $activeServices = $this->serviceRepository->countActive();


        // Render with default empty report table to avoid Twig errors
        return $this->render('ADMIN/reports/index.html.twig', [
            'totalClients' => $totalClients,
            'activeClients' => $activeClients,
            'suspendedClients' => $suspendedClients,
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'activeServices' => $activeServices,
            // Default empty report variables
            'table_headers' => [],
            'reports_data' => [],
            'report_title' => '',
            'report_type' => '',
        ]);
    }

    /**
     * Generate a report (users, orders, services, revenue)
     */
 #[Route('/generate', name: 'app_reports_generate', methods: ['GET'])]
public function generate(Request $request, ReportsDataController $reportsDataController): Response
{
    $reportType = $request->query->get('reportType');
    $fromDate = $request->query->get('from');
    $toDate = $request->query->get('to');

    // Debug - write to log file
    $logFile = __DIR__ . '/../../var/log/report_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Generate called. Type: $reportType\n", FILE_APPEND);

    if (!$reportType) {
        return $this->redirectToRoute('app_analytics_index');
    }

    $from = $fromDate ? \DateTime::createFromFormat('Y-m-d', $fromDate) : null;
    $to = $toDate ? \DateTime::createFromFormat('Y-m-d', $toDate) : null;

    file_put_contents($logFile, "About to forward to generateReportForAnalytics\n", FILE_APPEND);

    // Call ReportsDataController method directly
    return $reportsDataController->generateReportForAnalytics($reportType, $from, $to, $request);
}
    /**
     * Export a report to PDF or Excel
     */
    #[Route('/export', name: 'app_reports_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'pdf'); // pdf or excel
        $type = $request->query->get('type', 'users'); // users, orders, services, revenue
        $fromDate = $request->query->get('from');
        $toDate = $request->query->get('to');

        // Forward to ReportsDataController export
        return $this->forward('App\Controller\ReportsDataController::exportReport', [
            'format' => $format,
            'type' => $type,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }
}
