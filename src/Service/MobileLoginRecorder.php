<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Records client sign-ins from the mobile app (email/password or Google).
 */
final class MobileLoginRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function record(User $user): void
    {
        if (!$user->isMobileAppUser()) {
            return;
        }

        $user->setLastMobileLoginAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
