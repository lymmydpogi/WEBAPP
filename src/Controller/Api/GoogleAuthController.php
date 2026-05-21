<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\MobileAccessDeniedException;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use App\Service\MobileAppAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mobile Google Sign-In — same user rules as Admin\GoogleAuthController::check().
 */
final class GoogleAuthController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'GOOGLE_CLIENT_ID')] private string $googleClientId,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService $emailVerificationService,
        private MobileAppAccessService $mobileAppAccess,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $urlGenerator,
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

    #[Route('/api/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function authenticate(
        Request $request,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->apiError('Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $idToken = trim((string) ($data['idToken'] ?? ''));
        if ($idToken === '') {
            return $this->apiError('idToken is required.', Response::HTTP_BAD_REQUEST);
        }

        $client = HttpClient::create();
        $tokenInfoResponse = $client->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
            'query' => ['id_token' => $idToken],
        ]);

        if ($tokenInfoResponse->getStatusCode() >= 400) {
            return $this->apiError('Invalid Google ID token.', Response::HTTP_UNAUTHORIZED);
        }

        $tokenInfo = $tokenInfoResponse->toArray(false);
        $aud = (string) ($tokenInfo['aud'] ?? '');
        $allowedAudiences = array_filter(array_map('trim', explode(',', trim($this->googleClientId) . ',' . (string) ($_ENV['GOOGLE_ANDROID_CLIENT_ID'] ?? ''))));

        if ($aud === '' || !in_array($aud, $allowedAudiences, true)) {
            $this->logger->warning('Google ID token audience mismatch.', ['aud' => $aud, 'allowed' => $allowedAudiences]);

            return $this->apiError('Google token is not valid for this application.', Response::HTTP_UNAUTHORIZED);
        }

        $email = $tokenInfo['email'] ?? null;
        if (!is_string($email) || $email === '') {
            return $this->apiError('Google account does not provide an email address.', Response::HTTP_BAD_REQUEST);
        }

        $name = $tokenInfo['name'] ?? null;
        $givenName = $tokenInfo['given_name'] ?? null;
        $familyName = $tokenInfo['family_name'] ?? null;
        $googleEmailVerified = filter_var($tokenInfo['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $user = $userRepository->findOneBy(['email' => $email]);
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;
            $user = new User();
            $user->setEmail($email);
            $user->setName(is_string($name) ? $name : null);
            if (is_string($givenName) && $givenName !== '') {
                $user->setFirstName($givenName);
            }
            if (is_string($familyName) && $familyName !== '') {
                $user->setLastName($familyName);
            }
            $user->setRoles($this->mobileAppAccess->defaultMobileRegistrationRoles());
            $user->setStatus('active');
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->entityManager->persist($user);
        }

        if ($googleEmailVerified) {
            $user->markEmailAsVerified();
        }

        $this->entityManager->flush();

        try {
            $this->mobileAppAccess->assertCanUseMobileApp($user);
        } catch (MobileAccessDeniedException $e) {
            return $this->apiError($e->getMessage(), $e->getHttpStatus());
        }

        $this->sendGoogleConfirmationEmailSafely($user, $email, $isNewUser);

        $jwt = $jwtManager->create($user);

        return $this->apiSuccess('Signed in with Google.', [
            'token' => $jwt,
            'isNewUser' => $isNewUser,
            'user' => $this->serializeUser($user, $request),
        ]);
    }

    private function sendGoogleConfirmationEmailSafely(User $user, string $email, bool $isNewUser): void
    {
        $dashboardUrl = $this->urlGenerator->generate('app_client_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $this->emailVerificationService->sendGoogleSignInEmail($user, $dashboardUrl, $isNewUser);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Google sign-in confirmation email.', [
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);
        }
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
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'avatarUrl' => $avatarUrl,
            'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
