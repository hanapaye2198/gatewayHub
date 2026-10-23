<?php

namespace App\Http\Requests\Api;

use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class WalletTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->merchant() instanceof Merchant;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'size:3'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = implode(' ', $validator->errors()->all());

        throw new HttpResponseException(ApiResponse::error($message, 422));
    }
}
