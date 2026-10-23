<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PayMongoTransferException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $mayHaveBeenSubmitted = false,
        public readonly ?string $providerCode = null,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
