<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Shared admin bootstrap for fixtures and app:sync-initial-admin.
 */
final class InitialAdminBootstrap
{
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function applyAdminState(User $user): void
    {
        $user->setRoles(['ROLE_ADMIN']);
        $user->setStatus('active');
        $user->markEmailAsVerified();
    }

    public static function setPasswordFromPlain(
        User $user,
        string $plainPassword,
        UserPasswordHasherInterface $passwordHasher,
    ): void {
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
    }

    public static function assertPasswordValid(
        User $user,
        string $plainPassword,
        UserPasswordHasherInterface $passwordHasher,
    ): bool {
        return $passwordHasher->isPasswordValid($user, $plainPassword);
    }
}
