<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGcashMerchantSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'api_base_url' => ['required', 'url', 'max:255'],
            'redirect_success_url' => ['required', 'url', 'max:255'],
            'redirect_failure_url' => ['required', 'url', 'max:255'],
            'redirect_cancel_url' => ['required', 'url', 'max:255'],
        ];
    }
}
