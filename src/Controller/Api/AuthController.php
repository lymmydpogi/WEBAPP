<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\MobileAccessDeniedException;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use App\Service\MobileAppAccessService;
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
    public function __construct(
        private readonly MobileAppAccessService $mobileAppAccess,
    ) {
    }

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
        $passwordConfirm = (string) ($data['passwordConfirm'] ?? $data['confirmPassword'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));

        if (isset($data['roles']) && is_array($data['roles'])) {
            try {
                $this->mobileAppAccess->assertMobileRegistrationRoles($data['roles']);
            } catch (MobileAccessDeniedException $e) {
                return $this->apiError($e->getMessage(), $e->getHttpStatus());
            }
        }

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

        if ($password !== $passwordConfirm) {
            return $this->apiError('Password and confirmation do not match.', Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name !== '' ? $name : null);
        $user->setRoles($this->mobileAppAccess->defaultMobileRegistrationRoles());
        $user->setStatus('active');
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $verificationToken = $emailVerificationService->generateVerificationToken();
        $user->markEmailAsPendingVerification($verificationToken);

        try {
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Throwable) {
            return $this->apiError(
                'Could not save your account. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        $baseUrl = rtrim(
            (string) ($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? $request->getSchemeAndHttpHost()),
            '/',
        );
        $verificationWeb = $baseUrl . $this->generateUrl(
            'app_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::RELATIVE_PATH,
        );
        $verificationApi = $baseUrl . $this->generateUrl(
            'api_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::RELATIVE_PATH,
        );

        $emailSent = true;
        try {
            $emailVerificationService->sendVerificationEmail($user, $verificationWeb);
        } catch (\Throwable) {
            // Account is saved on Railway even if MAILER_* is not configured yet.
            $emailSent = false;
        }

        $message = $emailSent
            ? 'Registration successful. Please verify your email.'
            : 'Registration successful. Your account was created; verification email could not be sent yet.';

        return $this->apiSuccess($message, [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
            ],
            'verification' => [
                'web' => $verificationWeb,
                'api' => $verificationApi,
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->apiError('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->mobileAppAccess->assertCanUseMobileApp($user, true);
        } catch (MobileAccessDeniedException $e) {
            return $this->apiError($e->getMessage(), $e->getHttpStatus());
        }

        return $this->apiSuccess('Current user profile fetched.', [
            'user' => $this->serializeUser($user, $request),
        ]);
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    public function updateMe(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->apiError('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->mobileAppAccess->assertCanUseMobileApp($user, true);
        } catch (MobileAccessDeniedException $e) {
            return $this->apiError($e->getMessage(), $e->getHttpStatus());
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('firstName', $data)) {
            $user->setFirstName(trim((string) $data['firstName']) ?: null);
        }
        if (array_key_exists('lastName', $data)) {
            $user->setLastName(trim((string) $data['lastName']) ?: null);
        }
        if (array_key_exists('phone', $data)) {
            $user->setPhone(trim((string) $data['phone']) ?: null);
        }
        if (array_key_exists('address', $data)) {
            $user->setAddress(trim((string) $data['address']) ?: null);
        }
        if (array_key_exists('name', $data)) {
            $user->setName(trim((string) $data['name']) ?: null);
        }

        $first = trim((string) ($user->getFirstName() ?? ''));
        $last = trim((string) ($user->getLastName() ?? ''));
        if ($first !== '' || $last !== '') {
            $user->setName(trim($first . ' ' . $last));
        }

        $entityManager->flush();

        return $this->apiSuccess('Profile updated successfully.', [
            'user' => $this->serializeUser($user, $request),
        ]);
    }

    private function serializeUser(User $user, Request $request): array
    {
        $avatarUrl = null;
        if ($user->getAvatar()) {
            $avatarUrl = $request->getSchemeAndHttpHost() . '/uploads/avatars/' . $user->getAvatar();
        }

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'address' => $user->getAddress(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'avatarUrl' => $avatarUrl,
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}

