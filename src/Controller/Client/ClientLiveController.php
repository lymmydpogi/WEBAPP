<?php

namespace App\Controller\Client;

use App\Entity\User;
use App\Service\Admin\AdminLiveDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client/live')]
#[IsGranted('ROLE_USER')]
final class ClientLiveController extends AbstractController
{
    public function __construct(
        private readonly AdminLiveDataService $liveData,
    ) {
    }

    #[Route('/orders', name: 'client_live_orders', methods: ['GET'])]
    public function orders(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false], 401);
        }

        $snapshot = $this->liveData->getClientOrdersSnapshot($user);
        $response = $this->json([
            'success' => true,
            'orders' => $snapshot['orders'],
            'count' => $snapshot['count'],
            'revision' => $snapshot['revision'],
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
