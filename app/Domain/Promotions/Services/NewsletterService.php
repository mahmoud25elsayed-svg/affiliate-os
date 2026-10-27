<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Services;

use App\Models\NewsletterSubscriber;
use Illuminate\Validation\ValidationException;

class NewsletterService
{
    /**
     * Subscribe an email address to the store newsletter.
     *
     * @return array{success: bool, already_subscribed: bool, message: string, subscriber: NewsletterSubscriber}
     */
    public function subscribe(string $email, ?string $name = null, string $source = 'storefront'): array
    {
        $normalizedEmail = strtolower(trim($email));

        if (! filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'يرجى إدخال بريد إلكتروني صالح.',
            ]);
        }

        $existing = NewsletterSubscriber::query()
            ->where('email', $normalizedEmail)
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                return [
                    'success' => true,
                    'already_subscribed' => true,
                    'message' => 'أنت مشترك بالفعل في نشرتنا البريدية!',
                    'subscriber' => $existing,
                ];
            }

            // Re-activate previously unsubscribed user
            $existing->update([
                'is_active' => true,
                'name' => $name ?? $existing->name,
                'source' => $source,
                'unsubscribed_at' => null,
            ]);

            return [
                'success' => true,
                'already_subscribed' => false,
                'message' => 'تمت إعادة تفعيل اشتراكك في النشرة البريدية بنجاح!',
                'subscriber' => $existing,
            ];
        }

        /** @var NewsletterSubscriber $subscriber */
        $subscriber = NewsletterSubscriber::create([
            'email' => $normalizedEmail,
            'name' => $name,
            'is_active' => true,
            'source' => $source,
            'subscribed_at' => now(),
        ]);

        return [
            'success' => true,
            'already_subscribed' => false,
            'message' => 'تم اشتراكك بنجاح في النشرة البريدية!',
            'subscriber' => $subscriber,
        ];
    }

    /**
     * Unsubscribe an email from the newsletter.
     */
    public function unsubscribe(string $email): bool
    {
        $subscriber = NewsletterSubscriber::query()
            ->where('email', strtolower(trim($email)))
            ->first();

        if ($subscriber) {
            $subscriber->unsubscribe();

            return true;
        }

        return false;
    }
}
