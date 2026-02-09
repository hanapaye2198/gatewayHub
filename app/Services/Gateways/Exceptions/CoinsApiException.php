<?php

namespace App\Services\Gateways\Exceptions;

class CoinsApiException extends GatewayException
{
    public function __construct(
        string $message,
        protected ?int $httpStatus = null,
        protected ?array $responseBody = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
