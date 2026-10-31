<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Security\SsrfProtection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SafeWebhookUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail(__('رابط الـ Webhook غير صالح.'));

            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail(__('رابط الـ Webhook ليس رابط إنترنت صحيحاً.'));

            return;
        }

        if (! SsrfProtection::isSafeUrl($value)) {
            $fail(__('رابط الـ Webhook غير مسموح به لأسباب أمنية (حظر العناوين المحلية والشبكات الداخلية).'));
        }
    }
}
