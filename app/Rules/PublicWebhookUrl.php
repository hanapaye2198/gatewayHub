<?php

namespace App\Rules;

use App\Support\WebhookUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicWebhookUrl implements ValidationRule
{
    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! WebhookUrlGuard::isAllowed($value)) {
            $fail('The :attribute must be a public HTTP or HTTPS URL.');
        }
    }
}
