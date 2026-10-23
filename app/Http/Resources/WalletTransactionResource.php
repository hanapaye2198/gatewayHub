<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->payment;
        $payment = $payment instanceof Payment ? $payment : null;

        return [
            'id' => $this->id,
            'payment_id' => $payment?->id,
            'reference' => $payment?->reference_id,
            'provider_reference' => $payment?->provider_reference,
            'gateway' => $payment?->gateway_code,
            'status' => $payment?->status,
            'currency' => $this->currency,
            'product_amount' => $payment === null
                ? 0.0
                : ($payment->product_amount !== null ? (float) $payment->product_amount : (float) $payment->amount),
            'gross_amount' => $payment === null ? 0.0 : (float) $payment->amount,
            'platform_fee' => $payment === null ? 0.0 : (float) ($payment->platform_fee ?? 0),
            'net_amount' => (float) $this->amount,
            'wallet_status' => $this->is_settled ? 'settled' : 'available',
            'created_at' => $this->created_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
        ];
    }
}
