<?php

namespace App\Http\Resources;

use App\Models\MerchantPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MerchantPayout */
class WalletPayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $destinationAccount = is_array($this->destination_account) ? $this->destination_account : [];
        $accountNumber = preg_replace('/\s+/', '', (string) ($destinationAccount['number'] ?? ''));
        $maskedAccountNumber = $accountNumber === ''
            ? null
            : str_repeat('•', max(0, strlen($accountNumber) - 4)).substr($accountNumber, -4);

        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'reference_number' => $this->reference_number,
            'provider_reference_number' => $this->provider_reference_number,
            'provider_fee' => $this->provider_fee === null ? null : (float) $this->provider_fee,
            'destination_account' => [
                'name' => $destinationAccount['name'] ?? null,
                'bank_name' => $destinationAccount['bank_name'] ?? null,
                'bic' => $destinationAccount['bic'] ?? null,
                'number' => $maskedAccountNumber,
            ],
            'failure' => $this->failure_code === null && $this->failure_message === null ? null : [
                'code' => $this->failure_code,
                'message' => $this->failure_message,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
