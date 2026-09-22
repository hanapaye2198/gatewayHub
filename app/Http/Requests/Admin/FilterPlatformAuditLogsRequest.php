<?php

namespace App\Http\Requests\Admin;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterPlatformAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isPlatformOperator();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'action' => ['nullable', 'string', Rule::in(PlatformAuditLog::ACTIONS)],
            'merchant_id' => ['nullable', 'integer', 'exists:merchants,id'],
            'actor_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
