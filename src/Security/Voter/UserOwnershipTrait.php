<?php

namespace App\Security\Voter;

use App\Entity\User;

trait UserOwnershipTrait
{
    private function isSameUser(?User $owner, User $actor): bool
    {
        if (!$owner instanceof User) {
            return false;
        }

        $ownerId = $owner->getId();
        $actorId = $actor->getId();

        return $ownerId !== null && $actorId !== null && $ownerId === $actorId;
    }
}
