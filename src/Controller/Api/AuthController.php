<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AuthController extends AbstractController
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

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));

        if ($email === '' || $password === '') {
            return $this->apiError('Email and password are required.', Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->apiError('Please provide a valid email address.', Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->apiError('An account with this email already exists.', Response::HTTP_CONFLICT);
        }

        if (strlen($password) < 8) {
            return $this->apiError('Password must be at least 8 characters.', Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name !== '' ? $name : null);
        $user->setRoles(['ROLE_CLIENT']);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setIsVerified(false);

        $verificationToken = $emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);

        $entityManager->persist($user);
        $entityManager->flush();

        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $emailVerificationService->sendVerificationEmail($user, $verificationUrl);

        return $this->apiSuccess('Registration successful. Please verify your email.', [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
            ],
            'verification' => [
                'web' => $verificationUrl,
                'api' => $this->generateUrl('api_verify_email', ['token' => $verificationToken], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->apiError('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->apiSuccess('Current user profile fetched.', [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
            ],
        ]);
    }
}

