<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMerchantConvenienceFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'convenience_fee' => ['nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'convenience_fee.numeric' => 'The convenience fee must be a number.',
            'convenience_fee.min' => 'The convenience fee cannot be negative.',
            'convenience_fee.max' => 'The convenience fee cannot be greater than 100,000.',
            'convenience_fee.decimal' => 'The convenience fee can have at most two decimal places.',
        ];
    }
}
