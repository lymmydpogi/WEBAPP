<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class MobileAccessDeniedException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = Response::HTTP_FORBIDDEN,
    ) {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
