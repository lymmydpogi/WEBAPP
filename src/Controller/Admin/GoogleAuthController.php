<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class GoogleAuthController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'GOOGLE_CLIENT_ID')] private string $googleClientId,
        #[Autowire(env: 'GOOGLE_CLIENT_SECRET')] private string $googleClientSecret,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connect(): Response
    {
        $redirectUri = $this->urlGenerator->generate(
            'connect_google_check',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $query = http_build_query([
            'client_id' => $this->googleClientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return new RedirectResponse('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function check(
        Request $request,
        UserRepository $userRepository,
        Security $security
    ): Response {
        $code = $request->query->get('code');

        if (!$code) {
            $this->addFlash('error', 'Google sign-in failed or was canceled.');
            return $this->redirectToRoute('app_login_index');
        }

        $redirectUri = $this->urlGenerator->generate(
            'connect_google_check',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $client = HttpClient::create();

        // Exchange authorization code for access token
        $tokenResponse = $client->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $this->googleClientId,
                'client_secret' => $this->googleClientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $tokenData = $tokenResponse->toArray(false);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            $this->addFlash('error', 'Unable to complete Google authentication.');
            return $this->redirectToRoute('app_login_index');
        }

        // Fetch user info
        $userInfoResponse = $client->request('GET', 'https://www.googleapis.com/oauth2/v2/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        $userInfo = $userInfoResponse->toArray(false);
        $email = $userInfo['email'] ?? null;

        if (!$email) {
            $this->addFlash('error', 'Google account does not provide an email address.');
            return $this->redirectToRoute('app_login_index');
        }

        $name = $userInfo['name'] ?? null;

        // Find or create user as STAFF for staff/admin OAuth workflow
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setRoles(['ROLE_STAFF']);
            // Placeholder password; login happens via the authenticator after OAuth.
            $user->setPassword(bin2hex(random_bytes(32)));
        }
        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        if (!$user->getId()) {
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        // Log the user in using the configured authenticator on the main firewall
        $security->login($user, LoginFormAuthenticator::class, 'main');

        // Redirect like the normal form-login flow
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

        return $this->redirectToRoute('app_login_index');
    }
}

