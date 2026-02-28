<?php

namespace App\Http\Requests\Admin;

use App\Models\Gateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlatformGatewayConfigRequest extends FormRequest
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
        $gateway = $this->route('gateway');
        if (! $gateway instanceof Gateway) {
            return [
                'config' => ['required', 'array'],
            ];
        }

        $fields = config('gateway_credentials.'.$gateway->code, []);
        if (! is_array($fields) || $fields === []) {
            return [
                'config' => ['nullable', 'array'],
            ];
        }

        $rules = [
            'config' => ['required', 'array'],
        ];
        $existingConfig = is_array($gateway->config_json) ? $gateway->config_json : [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $fieldRules = [];
            $isRequired = (bool) ($field['required'] ?? false);
            $isMasked = (bool) ($field['masked'] ?? false);
            $hasExistingValue = array_key_exists($key, $existingConfig)
                && is_string($existingConfig[$key])
                && trim($existingConfig[$key]) !== '';

            if ($isRequired && ! ($isMasked && $hasExistingValue)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $type = $field['type'] ?? 'text';
            if ($type === 'select') {
                $options = $field['options'] ?? [];
                if (is_array($options) && $options !== []) {
                    $fieldRules[] = Rule::in(array_keys($options));
                }
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:255';

                $label = $field['label'] ?? '';
                if (is_string($label) && str_contains(strtolower($label), 'url')) {
                    $fieldRules[] = 'url';
                }
            }

            $rules['config.'.$key] = $fieldRules;
        }

        return $rules;
    }
}
