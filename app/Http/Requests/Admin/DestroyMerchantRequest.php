<?php

namespace App\Http\Requests\Admin;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroyMerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $merchant = $this->route('merchant');

        return $user instanceof User
            && $merchant instanceof Merchant
            && $user->can('delete', $merchant);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Merchant $merchant */
        $merchant = $this->route('merchant');

        return [
            'confirm_name' => ['required', 'string', Rule::in([$merchant->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.required' => __('Type the merchant name to confirm deletion.'),
            'confirm_name.in' => __('The merchant name does not match.'),
        ];
    }
}
