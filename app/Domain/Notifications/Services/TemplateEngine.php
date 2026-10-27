<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use Illuminate\Support\Arr;

class TemplateEngine
{
    /**
     * Interpolate {placeholder} tokens in a template string with given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function interpolate(string $template, array $data): string
    {
        return (string) preg_replace_callback('/\{([a-zA-Z0-9_\.]+)\}/', function (array $matches) use ($data): string {
            $key = $matches[1];
            $value = Arr::get($data, $key);

            if ($value === null) {
                return '';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            if (is_array($value)) {
                return implode(', ', array_map('strval', $value));
            }

            return '';
        }, $template);
    }

    /**
     * Return default starter templates for essential e-commerce events.
     *
     * @return array<string, array{subject: string, content: string, variables: list<string>}>
     */
    public function getDefaultTemplates(): array
    {
        return [
            'order_confirmed' => [
                'subject' => 'تأكيد طلبك رقم #{order_number}',
                'content' => 'مرحباً {customer_name}، تم تأكيد طلبك رقم #{order_number} بنجاح بقيمة {order_total}. سنقوم بتجهيزه وشحنه قريباً. شكراً لتسوقك من {store_name}!',
                'variables' => ['customer_name', 'order_number', 'order_total', 'store_name', 'items_count'],
            ],
            'order_shipped' => [
                'subject' => 'تم شحن طلبك رقم #{order_number}',
                'content' => 'أهلاً {customer_name}، طلبك رقم #{order_number} في الطريق إليك مع شركة الشحن {courier_name}. رقم التتبع: {tracking_number}. رابط التتبع: {tracking_url}',
                'variables' => ['customer_name', 'order_number', 'courier_name', 'tracking_number', 'tracking_url', 'store_name'],
            ],
            'order_delivered' => [
                'subject' => 'تم تسليم طلبك رقم #{order_number}',
                'content' => 'عزيزي {customer_name}، نأمل أن تكون راضياً عن مشترياتك في الطلب #{order_number}. يمكنك مشاركة تقييمك للمنتجات عبر متجرنا {store_name}.',
                'variables' => ['customer_name', 'order_number', 'store_name', 'review_url'],
            ],
            'order_cancelled' => [
                'subject' => 'إلغاء الطلب رقم #{order_number}',
                'content' => 'مرحباً {customer_name}، تم إلغاء الطلب رقم #{order_number}. سبب الإلغاء: {reason}. للاستفسار يرجى التواصل مع خدمة عملاء {store_name}.',
                'variables' => ['customer_name', 'order_number', 'reason', 'store_name'],
            ],
            'cart_abandoned' => [
                'subject' => 'هل نسيت سلتك في {store_name}؟',
                'content' => 'مرحباً {customer_name}، لاحظنا أنك تركت بعض المنتجات في سلتك! أكمل طلبك الآن قبل نفاد الكمية عبر الرابط: {recovery_url}',
                'variables' => ['customer_name', 'recovery_url', 'store_name', 'items_count', 'cart_total'],
            ],
            'otp_verification' => [
                'subject' => 'رمز التحقق الخاص بك',
                'content' => 'رمز التحقق لتسجيل الدخول في {store_name} هو: {otp_code}. صالح لمدة 10 دقائق. لا تشارك هذا الرمز مع أحد.',
                'variables' => ['otp_code', 'store_name'],
            ],
            'welcome' => [
                'subject' => 'مرحباً بك في {store_name}',
                'content' => 'أهلاً بك {customer_name} في {store_name}! يسعدنا انضمامك إلينا، نتمنى لك تجربة تسوق ممتعة.',
                'variables' => ['customer_name', 'store_name', 'store_url'],
            ],
        ];
    }
}
