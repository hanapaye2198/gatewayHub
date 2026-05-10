<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\UpdateMerchantWebhookSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MerchantWebhookSettingsController extends Controller
{
    public function update(UpdateMerchantWebhookSettingsRequest $request): JsonResponse
    {
        $merchant = $request->user()?->merchant;
        if ($merchant === null) {
            abort(403);
        }

        $validated = $request->validated();
        $webhookUrl = $validated['webhook_url'] ?? null;
        $inputSecret = $validated['webhook_secret'] ?? null;
        $regenerate = (bool) ($validated['regenerate_secret'] ?? false);

        $secret = null;
        if (is_string($inputSecret) && trim($inputSecret) !== '') {
            $secret = trim($inputSecret);
        } elseif ($regenerate || $merchant->webhook_secret === null) {
            $secret = Str::random(48);
        }

        $updates = ['webhook_url' => $webhookUrl];
        if ($secret !== null) {
            $updates['webhook_secret'] = $secret;
        }

        $merchant->forceFill($updates)->save();

        return response()->json([
            'success' => true,
            'merchant' => [
                'webhook_url' => $merchant->webhook_url,
            ],
            'webhook_secret' => $secret,
        ]);
    }
}
