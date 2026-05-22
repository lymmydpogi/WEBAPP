<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Exception\MobileAccessDeniedException;
use App\Repository\UserRepository;
use App\Service\MobileAppAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly MobileAppAccessService $mobileAppAccess,
    ) {
    }

    private function apiSuccess(string $message, array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ], $status);
    }

    private function apiError(string $message, int $status, array $errors = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    #[Route(path: '/login', name: 'app_login_index')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $accessDenied = $request->query->get('access_denied', false);

        // If this login was initiated from the client side (modal),
        // send the user back to the client landing page instead of
        // rendering the admin login template.
        if ($request->query->getBoolean('from_client')) {
            if ($error) {
                $this->addFlash('error', 'Invalid email or password.');
            }

            return $this->redirectToRoute('client_landing');
        }

        if ($this->getUser() && in_array('ROLE_ADMIN', $this->getUser()->getRoles(), true)) {
            return $this->redirectToRoute('app_home_index');
        }

        $errorMessage = null;
        if ($error) {
            $errorMessage = 'Wrong email or password. Please try again.';
            if (str_contains($error::class, 'Csrf')) {
                $errorMessage = 'Your session expired. Refresh the page and try again.';
            }
        }

        return $this->render('ADMIN/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'error_message' => $errorMessage,
            'access_denied' => $accessDenied
        ]);
    }

    #[Route(path: '/api/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !isset($data['email'], $data['password'])) {
            return $this->apiError(
                'Invalid request body. Expected JSON with "email" and "password".',
                Response::HTTP_BAD_REQUEST
            );
        }

        $email = trim((string) $data['email']);
        $user = $userRepository->findOneByEmail($email);

        if (!$user instanceof User || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->apiError(MobileAppAccessService::MSG_INVALID_CREDENTIALS, Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->mobileAppAccess->assertCanUseMobileApp($user);
        } catch (MobileAccessDeniedException $e) {
            return $this->apiError($e->getMessage(), $e->getHttpStatus());
        }

        $token = $jwtManager->create($user);

        $avatarUrl = null;
        if ($user->getAvatar()) {
            $avatarUrl = $request->getSchemeAndHttpHost() . '/uploads/avatars/' . $user->getAvatar();
        }

        return $this->apiSuccess('Login successful.', [
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
                'avatarUrl' => $avatarUrl,
                'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}

