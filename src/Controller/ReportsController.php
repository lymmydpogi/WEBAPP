<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\OrderRepository;
use App\Repository\ServicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_ADMIN')]
class ReportsController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private OrderRepository $orderRepository,
        private ServicesRepository $serviceRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'app_reports_index', methods: ['GET'])]
    public function index(): Response
    {
        $totalUsers = $this->userRepository->countAllClients(); // updated to count ROLE_CLIENT users
        $totalOrders = $this->orderRepository->count();
        $totalServices = $this->serviceRepository->count();
        $totalRevenue = $this->orderRepository->getTotalRevenue();
        $activeServices = $this->serviceRepository->count(['isActive' => true]);

        return $this->render('reports/index.html.twig', [
            'total_users' => $totalUsers,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue ?? 0,
            'active_services' => $activeServices,
        ]);
    }

    #[Route('/generate', name: 'app_reports_generate', methods: ['GET'])]
    public function generate(Request $request): Response
    {
        $reportType = $request->query->get('reportType');
        $fromDate = $request->query->get('from');
        $toDate = $request->query->get('to');

        if (!$reportType) {
            return $this->redirectToRoute('app_reports_index');
        }

        $from = $fromDate ? \DateTime::createFromFormat('Y-m-d', $fromDate) : null;
        $to = $toDate ? \DateTime::createFromFormat('Y-m-d', $toDate) : null;

        return $this->forward('App\\Controller\\ReportsDataController::generateReport', [
            'reportType' => $reportType,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/export', name: 'app_reports_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'pdf');
        $type = $request->query->get('type', 'users'); // changed from 'clients' to 'users'
        $fromDate = $request->query->get('from');
        $toDate = $request->query->get('to');

        return $this->forward('App\\Controller\\ReportsDataController::exportReport', [
            'format' => $format,
            'type' => $type,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }
}
