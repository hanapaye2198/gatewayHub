<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Rules\PublicWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isPlatformOperator();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:merchants,email'],
            'theme_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'qr_display_name' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:255', new PublicWebhookUrl],
        ];
    }
}
