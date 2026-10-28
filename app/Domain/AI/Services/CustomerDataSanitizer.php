<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Exceptions\CustomerPiiDetectedException;

class CustomerDataSanitizer
{
    /**
     * Regex for Egyptian and common phone numbers.
     * Matches 010..., 011..., 012..., 015... with or without country code +20/20.
     */
    protected const PHONE_REGEX = '/(?:\+?20\s?|0020\s?|0)?1[0125][0-9]{8}\b|(?:\+|00)[1-9][0-9]{7,13}\b/';

    /**
     * Regex for email addresses.
     */
    protected const EMAIL_REGEX = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';

    /**
     * Asserts that no customer PII (phone number or email) exists in the given input.
     * Throws CustomerPiiDetectedException if detected.
     */
    public function assertNoCustomerPii(string ...$texts): void
    {
        foreach ($texts as $text) {
            if (empty($text)) {
                continue;
            }

            if (preg_match(self::PHONE_REGEX, $text)) {
                throw new CustomerPiiDetectedException('customer phone number');
            }

            if (preg_match(self::EMAIL_REGEX, $text)) {
                throw new CustomerPiiDetectedException('customer email address');
            }
        }
    }

    /**
     * Redacts any detected phone numbers and emails from the text.
     */
    public function sanitize(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $clean = preg_replace(self::PHONE_REGEX, '[REDACTED_PHONE]', $text) ?? $text;

        return preg_replace(self::EMAIL_REGEX, '[REDACTED_EMAIL]', $clean) ?? $clean;
    }
}
