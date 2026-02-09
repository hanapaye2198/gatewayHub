<?php

namespace App\Services\Webhooks\Contracts;

use Illuminate\Http\Request;

interface WebhookReplayValidatorInterface
{
    /**
     * Validate that the webhook timestamp is within the allowed age window.
     *
     * @param  array<string, mixed>  $payload  Webhook JSON payload.
     */
    public function isValid(Request $request, array $payload): bool;
}
