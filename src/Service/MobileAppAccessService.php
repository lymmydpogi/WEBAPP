<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\MobileAccessDeniedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile app (APPDEV) may only be used by client/user accounts — not staff or admin.
 */
class MobileAppAccessService
{
    public const MSG_INVALID_CREDENTIALS = 'Invalid email or password.';
    public const MSG_ACCOUNT_INACTIVE = 'Your account is currently inactive. Please contact support.';
    public const MSG_STAFF_LOGIN = 'Staff accounts are not allowed to access the mobile app yet.';
    public const MSG_ADMIN_LOGIN = 'Admin accounts must use the web app.';
    public const MSG_STAFF_API = 'Staff accounts are not allowed to access mobile app features yet.';
    public const MSG_ADMIN_API = 'Admin accounts must use the web app.';

    public function assertCanUseMobileApp(User $user, bool $forApiRoute = false): void
    {
        if (!$user->isAccountActive()) {
            throw new MobileAccessDeniedException(
                self::MSG_ACCOUNT_INACTIVE,
                Response::HTTP_FORBIDDEN
            );
        }

        if ($user->isAdmin()) {
            throw new MobileAccessDeniedException(
                $forApiRoute ? self::MSG_ADMIN_API : self::MSG_ADMIN_LOGIN,
                Response::HTTP_FORBIDDEN
            );
        }

        if ($user->isStaff()) {
            throw new MobileAccessDeniedException(
                $forApiRoute ? self::MSG_STAFF_API : self::MSG_STAFF_LOGIN,
                Response::HTTP_FORBIDDEN
            );
        }

        if (!$user->isMobileAppUser()) {
            throw new MobileAccessDeniedException(
                'This account is not allowed to use the mobile app.',
                Response::HTTP_FORBIDDEN
            );
        }
    }

    public function assertMobileRegistrationRoles(array $requestedRoles): void
    {
        $elevated = array_intersect(['ROLE_ADMIN', 'ROLE_STAFF'], $requestedRoles);
        if ($elevated !== []) {
            throw new MobileAccessDeniedException(
                'Invalid registration request.',
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Default roles for mobile registration (never trust client payload).
     *
     * @return list<string>
     */
    public function defaultMobileRegistrationRoles(): array
    {
        return ['ROLE_CLIENT'];
    }
}
