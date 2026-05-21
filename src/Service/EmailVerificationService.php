<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_ADDRESS')] private string $fromAddress,
        #[Autowire(env: 'MAILER_FROM_NAME')] private string $fromName,
    ) {}

    /**
     * Generate a unique verification token
     */
    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(User $user, string $verificationUrl): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($user->getEmail()))
            ->subject('Please verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Optional confirmation after Google sign-in (user is already logged in).
     */
    public function sendGoogleSignInEmail(User $user, string $continueUrl, bool $isNewAccount): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($user->getEmail()))
            ->subject($isNewAccount
                ? 'Welcome to Campana Designs'
                : 'Signed in with Google — Campana Designs')
            ->htmlTemplate('emails/google_signin.html.twig')
            ->context([
                'user' => $user,
                'continueUrl' => $continueUrl,
                'isNewAccount' => $isNewAccount,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Verify a token: is_verified = true, verification_token = null.
     */
    public function verifyToken(string $token): ?User
    {
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return null;
        }

        $user->markEmailAsVerified();

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Check if a user needs verification
     */
    public function needsVerification(User $user): bool
    {
        return !$user->isVerified();
    }
}