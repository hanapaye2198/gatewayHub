<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePlatformAdministratorRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isSuperAdmin();
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
        $administrator = $this->route('administrator');
        $userId = is_numeric($administrator) ? (int) $administrator : null;

        return [
            ...$this->profileRules($userId),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
