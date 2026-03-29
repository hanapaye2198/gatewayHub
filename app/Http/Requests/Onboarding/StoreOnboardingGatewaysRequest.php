<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingGatewaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'gateway_ids' => ['nullable', 'array'],
            'gateway_ids.*' => ['integer', 'exists:gateways,id'],
        ];
    }
}
