<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Temporary login diagnostics. Enable with LOGIN_DEBUG=1 on Railway, then remove.
 */
final class LoginDebugSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isLoginRequest($request)) {
            return;
        }

        $email = mb_strtolower(trim((string) $request->request->get('_username', '')));
        $user = $email !== '' ? $this->userRepository->findOneByEmail($email) : null;
        $exception = $event->getException();

        $context = [
            'attempted_email' => $email,
            'user_found' => $user instanceof User,
            'failure_class' => $exception::class,
            'failure_message' => $exception->getMessage(),
        ];

        if ($user instanceof User) {
            $context['user_id'] = $user->getId();
            $context['stored_email'] = $user->getEmail();
            $context['roles'] = $user->getRoles();
            $context['status'] = $user->getStatus();
            $context['is_verified'] = $user->isVerified();
            $context['is_account_active'] = $user->isAccountActive();
            $context['has_password_hash'] = $user->getPassword() !== null && $user->getPassword() !== '';
            $plain = (string) $request->request->get('_password', '');
            $context['password_validation_passed'] = $plain !== ''
                && $this->passwordHasher->isPasswordValid($user, $plain);
        }

        $this->logger->warning('[LOGIN_DEBUG] Web login failed.', $context);
    }

    private function isDebugEnabled(): bool
    {
        $flag = $_ENV['LOGIN_DEBUG'] ?? $_SERVER['LOGIN_DEBUG'] ?? getenv('LOGIN_DEBUG');

        return $flag === '1' || $flag === 'true';
    }

    private function isLoginRequest(Request $request): bool
    {
        return $request->isMethod('POST')
            && str_starts_with($request->getPathInfo(), '/login');
    }
}
