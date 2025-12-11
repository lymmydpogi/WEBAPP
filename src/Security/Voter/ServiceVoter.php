<?php

namespace App\Security\Voter;

use App\Entity\Services;
use App\Entity\User;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ServiceVoter extends Voter
{
    public const EDIT = 'SERVICE_EDIT';
    public const DELETE = 'SERVICE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof Services;
    }

    protected function voteOnAttribute(string $attribute, mixed $service, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admin always allowed
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can only modify services they created
        return match ($attribute) {
            self::EDIT => $service->getCreatedBy() === $user,
            self::DELETE => $service->getCreatedBy() === $user,
            default => false
        };
    }
}
