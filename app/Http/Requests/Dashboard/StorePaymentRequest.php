<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'gateway' => ['required', 'string', 'exists:gateways,code'],
        ];

        if (in_array($this->input('gateway'), ['qrph', 'gcash', 'maya', 'grab_pay', 'shopeepay'], true)) {
            $rules['amount'][] = 'decimal:0,2';
            $rules['currency'][] = 'in:PHP';
            $rules['amount'][] = 'min:1';
            $rules['amount'][] = $this->input('gateway') === 'qrph' ? 'max:9999999.99' : 'max:100000';
        }

        return $rules;
    }
}
