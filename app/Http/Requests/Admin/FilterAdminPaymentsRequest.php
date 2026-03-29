<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterAdminPaymentsRequest extends FormRequest
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
            'merchant_id' => [
                'nullable',
                'integer',
                Rule::exists('merchants', 'id'),
            ],
            'gateway_code' => ['nullable', 'string', Rule::exists('gateways', 'code')],
            'status' => ['nullable', 'string', Rule::in(['pending', 'paid', 'failed', 'refunded', 'failed_after_paid'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }
}
