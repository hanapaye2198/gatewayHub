<?php

namespace App\Http\Requests\Api;

use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateWalletPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->merchant() instanceof Merchant;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /** @return array<string, array<int, mixed>|string> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(['instapay', 'pesonet'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:10000000'],
            'currency' => ['sometimes', 'required', 'string', Rule::in(['PHP'])],
            'destination_account' => ['required', 'array:number,name,bic,bank_name'],
            'destination_account.number' => ['required', 'string', 'min:4', 'max:34', 'regex:/^[0-9+ -]+$/'],
            'destination_account.name' => ['required', 'string', 'min:2', 'max:140'],
            'destination_account.bic' => ['required', 'string', 'min:6', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'destination_account.bank_name' => ['nullable', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Send a unique Idempotency-Key header for each payout request.',
            'destination_account.number.required' => 'A destination bank or e-wallet account number is required.',
            'destination_account.name.required' => 'The destination account holder name is required.',
            'destination_account.bic.required' => 'Select the destination institution BIC returned by the institutions endpoint.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = implode(' ', $validator->errors()->all());

        throw new HttpResponseException(ApiResponse::error($message, 422));
    }
}
