<?php

namespace App\Http\Requests\Admin;

use App\Models\Merchant;
use App\Models\User;
use App\Rules\PublicWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMerchantRequest extends FormRequest
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
        $merchant = $this->route('merchant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('merchants', 'email')->ignore($merchant instanceof Merchant ? $merchant->id : $merchant),
            ],
            'theme_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'qr_display_name' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:255', new PublicWebhookUrl],
        ];
    }
}
