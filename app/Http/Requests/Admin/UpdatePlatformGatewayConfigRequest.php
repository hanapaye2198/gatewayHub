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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $gateway = $this->route('gateway');
            if (! $gateway instanceof Gateway || $gateway->code !== 'coins') {
                return;
            }

            $config = $this->input('config');
            if (! is_array($config)) {
                return;
            }

            foreach (['client_id', 'client_secret', 'api_key', 'api_secret', 'webhook_secret'] as $key) {
                $value = $config[$key] ?? null;
                if (! is_string($value)) {
                    continue;
                }

                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }

                if ($this->isPlaceholderCredentialValue($trimmed)) {
                    $validator->errors()->add(
                        'config.'.$key,
                        'Placeholder credentials are not allowed. Enter real Coins.ph values.'
                    );
                }
            }
        });
    }

    private function isPlaceholderCredentialValue(string $value): bool
    {
        $normalized = strtolower(trim($value));
        $placeholders = [
            'your_real_client_id',
            'your_real_client_secret',
            'your_real_webhook_secret',
            'your_client_id',
            'your_client_secret',
            'your_webhook_secret',
            'your_api_key',
            'your_api_secret',
            'change_me',
            'replace_me',
        ];

        return in_array($normalized, $placeholders, true);
    }
}
