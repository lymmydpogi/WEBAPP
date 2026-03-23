<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class EmailVerificationController extends AbstractController
{
    private function apiSuccess(string $message, array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ], $status);
    }

    private function apiError(string $message, int $status, array $errors = []): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verify(string $token, EmailVerificationService $emailVerificationService): Response
    {
        $user = $emailVerificationService->verifyToken($token);

        if (!$user) {
            return $this->render('emails/verification.html.twig', [
                'verified' => false,
                'error' => 'Invalid or expired verification token.',
            ]);
        }

        return $this->render('emails/verification.html.twig', [
            'verified' => true,
            'user' => $user,
        ]);
    }

    #[Route('/api/verify-email/{token}', name: 'api_verify_email', methods: ['GET'])]
    public function verifyApi(string $token, EmailVerificationService $emailVerificationService): JsonResponse
    {
        $user = $emailVerificationService->verifyToken($token);

        if (!$user) {
            return $this->apiError('Invalid or expired verification token.', Response::HTTP_BAD_REQUEST);
        }

        return $this->apiSuccess('Email verified successfully.', [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }
}