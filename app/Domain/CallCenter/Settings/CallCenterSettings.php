<?php

declare(strict_types=1);

namespace App\Domain\CallCenter\Settings;

use App\Support\Settings\Settings;

class CallCenterSettings extends Settings
{
    /**
     * Maximum call attempts before transitioning order to unreachable or cancelled.
     */
    public int $max_call_attempts = 3;

    /**
     * Retry interval schedule in minutes for attempts 1, 2, 3...
     *
     * @var list<int>
     */
    public array $attempt_intervals_minutes = [60, 180, 360];

    /**
     * Lock duration in minutes when an agent claims an order.
     */
    public int $lock_duration_minutes = 10;

    /**
     * Automatically mark order unreachable when max attempts are exhausted.
     */
    public bool $auto_unreachable_on_exhaust = true;

    /**
     * Default call script template for agents.
     */
    public string $call_script = 'السلام عليكم، أهلاً بحضرتك يا فندم مع حضرتك [اسم الموظف] من خدمة عملاء المتجر. بخصوص طلبك الأخير، حابب أتأكد من عنوان التوصيل بالتفصيل وتأكيد موعد استلام الشحنة خلال 48 ساعة.';

    public static function group(): string
    {
        return 'call_center';
    }

    public function getMaxCallAttempts(): int
    {
        return $this->max_call_attempts;
    }

    public function getIntervalForAttempt(int $attemptNumber): int
    {
        $index = max(0, $attemptNumber - 1);

        return $this->attempt_intervals_minutes[$index] ?? 120;
    }

    public function getLockDurationMinutes(): int
    {
        return $this->lock_duration_minutes;
    }

    public function shouldAutoUnreachable(): bool
    {
        return $this->auto_unreachable_on_exhaust;
    }
}
