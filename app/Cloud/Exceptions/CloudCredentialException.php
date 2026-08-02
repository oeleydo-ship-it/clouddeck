<?php

namespace App\Cloud\Exceptions;

use RuntimeException;

final class CloudCredentialException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly ?int $providerStatus = null,
    ) {
        parent::__construct($message);
    }
}
