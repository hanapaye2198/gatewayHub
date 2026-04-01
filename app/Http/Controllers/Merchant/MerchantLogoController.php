<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StoreMerchantLogoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MerchantLogoController extends Controller
{
    /**
     * Store merchant logo under storage/app/public/merchants/{id}.
     */
    public function store(StoreMerchantLogoRequest $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $user->merchant;
        if ($merchant === null) {
            abort(403);
        }

        $previous = $merchant->logo_path;
        $path = $request->file('logo')->store("merchants/{$merchant->id}", 'public');

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $merchant->forceFill(['logo_path' => $path])->save();

        return response()->json([
            'success' => true,
            'merchant' => $merchant->fresh()->brandingForApi(),
        ]);
    }
}
