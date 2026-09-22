<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateMerchantUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();
        $merchant = $this->route('merchant');

        return $user instanceof User
            && $merchant instanceof Merchant
            && $user->can('manageUsers', $merchant);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $merchantUser = $this->route('user');
        $userId = is_numeric($merchantUser) ? (int) $merchantUser : null;

        return [
            ...$this->profileRules($userId),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
