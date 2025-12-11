<?php

namespace App\Security\Voter;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class OrderVoter extends Voter
{
    public const EDIT = 'ORDER_EDIT';
    public const DELETE = 'ORDER_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $order, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admin always allowed
        if ($user->isAdmin()) {
            return true;
        }

        // Staff only allowed if they created the order
        return match ($attribute) {
            self::EDIT => $order->getCreatedBy() === $user,
            self::DELETE => $order->getCreatedBy() === $user,
            default => false
        };
    }
}
