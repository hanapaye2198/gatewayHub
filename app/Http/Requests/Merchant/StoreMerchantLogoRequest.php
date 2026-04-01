<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->merchant_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'max:2048', 'mimes:jpeg,jpg,png'],
        ];
    }
}
