<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;

final class GoogleAuthController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'GOOGLE_CLIENT_ID')] private string $googleClientId,
        #[Autowire(env: 'GOOGLE_CLIENT_SECRET')] private string $googleClientSecret,
        #[Autowire(env: 'default:oauth_google_redirect_uri:OAUTH_GOOGLE_REDIRECT_URI')] private string $oauthRedirectUri,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService $emailVerificationService,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connect(Request $request): Response
    {
        if (!$this->hasGoogleCredentials()) {
            $this->addFlash('error', 'Google sign-in is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');

            return $this->redirectToRoute('app_login_index');
        }

        $redirectUri = $this->resolveRedirectUri($request);

        // select_account = pick Google account; consent = "Sign in to …" + Continue screen
        $query = http_build_query([
            'client_id' => trim($this->googleClientId),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account consent',
            'include_granted_scopes' => 'true',
        ]);

        return new RedirectResponse('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(
        Request $request,
        UserRepository $userRepository,
        Security $security,
    ): Response {
        $oauthError = $request->query->get('error');
        if (is_string($oauthError) && $oauthError !== '') {
            $description = $request->query->get('error_description', 'Google sign-in was not completed.');
            $this->addFlash('error', is_string($description) ? $description : 'Google sign-in was not completed.');

            return $this->redirectToRoute('app_login_index');
        }

        $code = $request->query->get('code');
        if (!is_string($code) || $code === '') {
            $this->addFlash('error', 'Google sign-in failed or was canceled.');

            return $this->redirectToRoute('app_login_index');
        }

        if (!$this->hasGoogleCredentials()) {
            $this->addFlash('error', 'Google sign-in is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');

            return $this->redirectToRoute('app_login_index');
        }

        $redirectUri = $this->resolveRedirectUri($request);
        $client = HttpClient::create();

        $tokenResponse = $client->request('POST', 'https://oauth2.googleapis.com/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'code' => $code,
                'client_id' => trim($this->googleClientId),
                'client_secret' => trim($this->googleClientSecret),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]),
        ]);

        $statusCode = $tokenResponse->getStatusCode();
        $tokenData = $tokenResponse->toArray(false);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            $this->logger->warning('Google OAuth token exchange failed.', [
                'status' => $statusCode,
                'error' => $tokenData['error'] ?? null,
                'error_description' => $tokenData['error_description'] ?? null,
                'redirect_uri' => $redirectUri,
            ]);

            $message = 'Unable to complete Google authentication.';
            if ($this->getParameter('kernel.debug')) {
                $detail = $tokenData['error_description'] ?? $tokenData['error'] ?? null;
                if (is_string($detail) && $detail !== '') {
                    $message .= ' (' . $detail . ')';
                }
            }

            $this->addFlash('error', $message);

            return $this->redirectToRoute('app_login_index');
        }

        $userInfoResponse = $client->request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        if ($userInfoResponse->getStatusCode() >= 400) {
            $this->addFlash('error', 'Unable to read your Google profile.');

            return $this->redirectToRoute('app_login_index');
        }

        $userInfo = $userInfoResponse->toArray(false);
        $email = $userInfo['email'] ?? null;

        if (!is_string($email) || $email === '') {
            $this->addFlash('error', 'Google account does not provide an email address.');

            return $this->redirectToRoute('app_login_index');
        }

        $name = $userInfo['name'] ?? null;
        $googleEmailVerified = (bool) ($userInfo['email_verified'] ?? false);

        $user = $userRepository->findOneBy(['email' => $email]);
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;
            $user = new User();
            $user->setEmail($email);
            $user->setName(is_string($name) ? $name : null);
            $user->setRoles(['ROLE_CLIENT']);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $this->entityManager->persist($user);
        }

        // Google sign-in: log in immediately. Google OAuth proves email ownership.
        if ($googleEmailVerified) {
            $user->markEmailAsVerified();
        }

        $this->entityManager->flush();

        $this->sendGoogleConfirmationEmailSafely($user, $email, $isNewUser);

        return $this->loginAndRedirect($security, $user, $isNewUser);
    }

    private function sendGoogleConfirmationEmailSafely(User $user, string $email, bool $isNewUser): void
    {
        $dashboardUrl = $this->generateUrl('app_client_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $this->emailVerificationService->sendGoogleSignInEmail($user, $dashboardUrl, $isNewUser);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Google sign-in confirmation email.', [
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function loginAndRedirect(Security $security, User $user, bool $isNewUser): Response
    {
        $security->login($user, firewallName: 'main', badges: [new RememberMeBadge()]);

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $this->redirectToRoute('app_home_index');
        }
        if (in_array('ROLE_STAFF', $roles, true)) {
            return $this->redirectToRoute('app_services_index');
        }
        if (in_array('ROLE_CLIENT', $roles, true)) {
            return $this->redirectToRoute('app_client_dashboard');
        }

        $this->addFlash('error', 'Your account has no assigned role. Please contact support.');
        if (!$isNewUser) {
            return $this->redirectToRoute('app_login_index');
        }

        return $this->redirectToRoute('client_landing');
    }

    private function hasGoogleCredentials(): bool
    {
        return trim($this->googleClientId) !== '' && trim($this->googleClientSecret) !== '';
    }

    private function resolveRedirectUri(Request $request): string
    {
        $configured = trim($this->oauthRedirectUri);
        if ($configured !== '') {
            return $configured;
        }

        return $request->getSchemeAndHttpHost()
            . $this->urlGenerator->generate(
                'connect_google_check',
                [],
                UrlGeneratorInterface::ABSOLUTE_PATH
            );
    }
}
