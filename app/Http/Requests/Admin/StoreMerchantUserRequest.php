<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

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
        if (! $this->exists('is_active')) {
            $this->merge(['is_active' => true]);

            return;
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
