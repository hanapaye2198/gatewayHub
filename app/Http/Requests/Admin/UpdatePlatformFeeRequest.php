<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformFeeRequest extends FormRequest
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
            'percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'percentage.required' => 'Enter a platform fee percentage.',
            'percentage.numeric' => 'The platform fee must be a number.',
            'percentage.min' => 'The platform fee cannot be negative.',
            'percentage.max' => 'The platform fee cannot be greater than 100%.',
            'percentage.decimal' => 'The platform fee can have at most two decimal places.',
        ];
    }
}
