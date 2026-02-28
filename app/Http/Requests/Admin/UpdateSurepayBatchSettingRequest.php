<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSurepayBatchSettingRequest extends FormRequest
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
            'batch_interval_value' => ['required', 'integer', 'min:1', 'max:100000'],
            'batch_interval_unit' => ['required', 'in:seconds,minutes,days,weeks'],
            'tax_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'batch_interval_value.required' => 'Batch interval value is required.',
            'batch_interval_unit.required' => 'Batch interval unit is required.',
            'tax_percentage.required' => 'Tax percentage is required.',
        ];
    }
}
