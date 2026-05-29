<?php

namespace App\Controller\Admin;

use App\Service\Admin\AdminLiveDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/live')]
#[IsGranted('ROLE_STAFF')]
final class AdminLiveController extends AbstractController
{
    public function __construct(
        private readonly AdminLiveDataService $liveData,
    ) {
    }

    #[Route('/orders', name: 'admin_live_orders', methods: ['GET'])]
    public function orders(): JsonResponse
    {
        $snapshot = $this->liveData->getOrdersSnapshot();

        return $this->jsonResponse([
            'success' => true,
            'orders' => $snapshot['orders'],
            'count' => $snapshot['count'],
            'maxOrderId' => $snapshot['maxOrderId'],
            'revision' => $snapshot['revision'],
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/dashboard', name: 'admin_live_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $data = $this->liveData->getDashboardSnapshot();

        return $this->jsonResponse([
            'success' => true,
            ...$data,
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = $this->json($data, $status);
        $response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
