<?php

namespace App\Http\Requests\Api;

use App\Http\Responses\ApiResponse;
use App\Models\Merchant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $merchant = $this->merchant();

        return $merchant instanceof Merchant;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => ['required', 'string', 'size:3', Rule::in(config('payments.currencies', ['PHP']))],
            'gateway' => ['required', 'string', 'max:50'],
            'reference' => ['required', 'string', 'max:255'],
            'checkout' => ['sometimes', 'boolean'],
            'product_name' => ['nullable', 'string', 'max:255'],
        ];

        if (in_array($this->input('gateway'), ['qrph', 'gcash', 'maya', 'grab_pay', 'shopeepay'], true)) {
            $rules['amount'][] = 'decimal:0,2';
            $rules['currency'][] = Rule::in(['PHP']);
            $rules['amount'][] = 'min:1';
            $rules['amount'][] = $this->input('gateway') === 'qrph' ? 'max:9999999.99' : 'max:100000';
        }

        if ($this->input('gateway') === 'qrph') {
            $rules['qr_expiry_seconds'] = ['sometimes', 'integer', 'between:60,9000'];
        } else {
            $rules['qr_expiry_seconds'] = ['prohibited'];
        }

        return $rules;
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = implode(' ', $validator->errors()->all());

        throw new HttpResponseException(ApiResponse::error($message, 422));
    }
}
