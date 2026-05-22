<?php

namespace App\Controller\Api;

use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/** Public client services catalog (active + inactive). */
final class ClientServicesController extends AbstractController
{
    #[Route('/api/client/services', name: 'api_client_services_list', methods: ['GET'])]
    public function list(ServicesRepository $servicesRepository): JsonResponse
    {
        // All services for browsing; isOrderable / visibleInApp flags which can be ordered.
        $entities = $servicesRepository->findAllOrderedByName();
        $services = array_map(ServicesRepository::serializeForClient(...), $entities);

        return $this->json([
            'success' => true,
            'message' => 'Services loaded.',
            'data' => [
                'services' => $services,
                'orderableCount' => count(array_filter($services, static fn (array $s): bool => ($s['isOrderable'] ?? false) === true)),
            ],
            'errors' => [],
        ]);
    }

    #[Route('/api/client/services/{slug}', name: 'api_client_services_show', methods: ['GET'])]
    public function show(string $slug, ServicesRepository $servicesRepository): JsonResponse
    {
        $service = $servicesRepository->findOneBySlug($slug);

        if (!$service) {
            return $this->json([
                'success' => false,
                'message' => 'Service not found.',
                'data' => null,
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'message' => 'Service loaded.',
            'data' => [
                'service' => ServicesRepository::serializeForClient($service),
            ],
            'errors' => [],
        ]);
    }
}
