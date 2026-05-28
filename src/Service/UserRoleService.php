<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Admin-only role changes (e.g. client → staff).
 */
final class UserRoleService
{
    public const ROLE_CLIENT = 'ROLE_CLIENT';
    public const ROLE_STAFF = 'ROLE_STAFF';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    /** @return array<string, string> label => role */
    public function assignableRoleChoices(User $target): array
    {
        if ($target->isAdmin()) {
            return ['Administrator' => self::ROLE_ADMIN];
        }

        return [
            'Client' => self::ROLE_CLIENT,
            'Staff' => self::ROLE_STAFF,
        ];
    }

    public function assertCanAssignRole(User $actor, User $target, string $newRole): void
    {
        if (!$actor->isAdmin()) {
            throw new AccessDeniedException('Only administrators can change user roles.');
        }

        if ($actor->getId() === $target->getId() && $newRole !== self::ROLE_ADMIN && $target->isAdmin()) {
            throw new AccessDeniedException('You cannot remove your own administrator access.');
        }

        if ($target->isAdmin() && $newRole !== self::ROLE_ADMIN) {
            throw new AccessDeniedException('Administrator accounts cannot be changed to client or staff here.');
        }

        $allowed = array_values($this->assignableRoleChoices($target));
        if (!in_array($newRole, $allowed, true)) {
            throw new AccessDeniedException('That role is not allowed for this user.');
        }
    }

    public function applyRole(User $user, string $role): void
    {
        $user->setRoles([$role]);

        if ($role === self::ROLE_STAFF || $role === self::ROLE_ADMIN) {
            $user->setStatus('active');
            $user->markEmailAsVerified();
        }
    }

    public function canPromoteToStaff(User $user): bool
    {
        return !$user->isAdmin() && !$user->isStaff() && in_array(self::ROLE_CLIENT, $user->getRoles(), true);
    }
}
