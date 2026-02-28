<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTunnelWalletSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'auto_settle_to_real_wallet' => ['required', 'boolean'],
            'default_currency' => ['required', 'string', 'size:3'],
            'tunnel_client_id' => ['required', 'string', 'max:255'],
            'tunnel_client_secret' => ['nullable', 'string', 'max:255'],
            'tunnel_webhook_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tunnel_client_id.required' => 'Client ID is required.',
            'default_currency.size' => 'Default currency must be a 3-letter code.',
        ];
    }
}
