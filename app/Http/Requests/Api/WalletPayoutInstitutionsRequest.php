<?php

namespace App\Http\Requests\Api;

use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class WalletPayoutInstitutionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->merchant() instanceof Merchant;
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['instapay', 'pesonet'])],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = implode(' ', $validator->errors()->all());

        throw new HttpResponseException(ApiResponse::error($message, 422));
    }
}
