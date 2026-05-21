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
        $services = array_map(
            ServicesRepository::serializeForClient(...),
            $servicesRepository->findAllOrderedByName()
        );

        return $this->json([
            'success' => true,
            'message' => 'Services loaded.',
            'data' => ['services' => $services],
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
