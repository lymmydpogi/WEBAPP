<?php

namespace App\Controller\Firebase;

use App\Entity\User;
use App\Service\Firebase\FirebaseAdminFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FirebaseCustomTokenController extends AbstractController
{
    public function __construct(
        private readonly FirebaseAdminFactory $firebase,
    ) {
    }

    #[Route('/firebase/custom-token', name: 'firebase_custom_token_web', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function webToken(): JsonResponse
    {
        return $this->createTokenResponse();
    }

    #[Route('/api/firebase/custom-token', name: 'firebase_custom_token_api', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function apiToken(): JsonResponse
    {
        return $this->createTokenResponse();
    }

    private function createTokenResponse(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $auth = $this->firebase->auth();
        if ($auth === null) {
            return $this->json([
                'success' => false,
                'message' => 'Firebase Admin is not configured on the server.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $uid = 'campana_' . $user->getId();
        $token = $auth->createCustomToken($uid, [
            'campanaUserId' => $user->getId(),
            'roles' => $user->getRoles(),
        ]);

        return $this->json([
            'success' => true,
            'token' => $token->toString(),
        ]);
    }
}
