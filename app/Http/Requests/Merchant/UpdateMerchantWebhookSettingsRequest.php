<?php

namespace App\Http\Requests\Merchant;

use App\Rules\PublicWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantWebhookSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->merchant !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'webhook_url' => ['nullable', 'url', 'max:255', new PublicWebhookUrl],
            'webhook_secret' => ['nullable', 'string', 'max:65535'],
            'regenerate_secret' => ['sometimes', 'boolean'],
        ];
    }
}
