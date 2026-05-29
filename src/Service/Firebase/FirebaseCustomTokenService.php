<?php

namespace App\Service\Firebase;

use App\Entity\User;

final class FirebaseCustomTokenService
{
    public function __construct(
        private readonly FirebaseAdminFactory $firebase,
    ) {
    }

    /**
     * @return array{token: string}|null
     */
    public function createForUser(User $user): ?array
    {
        $auth = $this->firebase->auth();
        if ($auth === null) {
            return null;
        }

        $uid = (string) $user->getId();
        $claims = [
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ];

        $token = $auth->createCustomToken($uid, $claims);

        return ['token' => $token->toString()];
    }
}
