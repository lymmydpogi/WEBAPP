<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class ChatMessageException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
