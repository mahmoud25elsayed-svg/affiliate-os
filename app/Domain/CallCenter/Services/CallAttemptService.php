<?php

declare(strict_types=1);

namespace App\Domain\CallCenter\Services;

use App\Domain\CallCenter\Enums\CallOutcome;
use App\Domain\CallCenter\Settings\CallCenterSettings;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\CallAttempt;
use App\Models\OrderEvent;
use App\Models\User;
use App\Support\Enums\ReasonCode;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Lunar\Models\Order;

class CallAttemptService
{
    public function __construct(
        protected OrderTransitionService $transitionService
    ) {}

    /**
     * Record a call attempt and apply business rules for state transitions and scheduling.
     *
     * @param  array{
     *     outcome: string,
     *     notes?: string|null,
     *     scheduled_callback_at?: string|null,
     *     duration_seconds?: int|null,
     *     recording_url?: string|null
     * }  $data
     */
    public function recordAttempt(Order $order, User $agent, array $data): CallAttempt
    {
        $outcomeEnum = CallOutcome::tryFrom($data['outcome']);
        if (! $outcomeEnum) {
            throw new InvalidArgumentException(__('نتيجة المكالمة غير صالحة: :outcome', ['outcome' => $data['outcome']]));
        }

        $settings = CallCenterSettings::load();
        $attemptNumber = (int) $order->call_attempts_count + 1;
        $maxAttempts = $settings->getMaxCallAttempts();
        $actor = OrderActor::staff(user: $agent, role: 'call_center');
        $notes = $data['notes'] ?? '';

        /** @var CallAttempt $attempt */
        $attempt = CallAttempt::create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'user_id' => $agent->id,
            'attempt_number' => $attemptNumber,
            'outcome' => $outcomeEnum->value,
            'notes' => $notes,
            'scheduled_callback_at' => ! empty($data['scheduled_callback_at']) ? Carbon::parse($data['scheduled_callback_at']) : null,
            'duration_seconds' => isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            'recording_url' => $data['recording_url'] ?? null,
        ]);

        $nextAttemptAt = null;
        if ($outcomeEnum === CallOutcome::POSTPONED) {
            $nextAttemptAt = ! empty($data['scheduled_callback_at'])
                ? Carbon::parse($data['scheduled_callback_at'])
                : Carbon::now()->addHours(2);
        } elseif (in_array($outcomeEnum, [CallOutcome::NO_ANSWER, CallOutcome::BUSY, CallOutcome::UNREACHABLE], true)) {
            if ($attemptNumber < $maxAttempts || ! $settings->shouldAutoUnreachable()) {
                $interval = $settings->getIntervalForAttempt($attemptNumber);
                $nextAttemptAt = Carbon::now()->addMinutes($interval);
            }
        }

        // Update call-center tracking columns in database
        Order::query()->where('id', $order->id)->update([
            'call_attempts_count' => $attemptNumber,
            'assigned_to' => null,
            'locked_until' => null,
            'next_attempt_at' => $nextAttemptAt,
        ]);

        $order->refresh();

        // Process outcome & transitions
        switch ($outcomeEnum) {
            case CallOutcome::CONFIRMED:
                $this->transitionService->transition(
                    order: $order,
                    to: OrderStatus::CONFIRMED,
                    reason: null,
                    actor: $actor,
                    meta: ['call_attempt_id' => $attempt->id],
                    note: __('تم تأكيد الطلب بنجاح عبر مكالمة هاتفية بواسطة الموظف :name.', ['name' => $agent->name])
                );
                break;

            case CallOutcome::CUSTOMER_CANCELLED:
                $this->transitionService->transition(
                    order: $order,
                    to: OrderStatus::CANCELLED_BY_CUSTOMER,
                    reason: ReasonCode::CUSTOMER_CHANGED_MIND,
                    actor: $actor,
                    meta: ['call_attempt_id' => $attempt->id],
                    note: __('إلغاء الطلب بناءً على رغبة العميل أثناء المكالمة. ملاحظات: :notes', ['notes' => $notes])
                );
                break;

            case CallOutcome::WRONG_NUMBER:
                $this->transitionService->transition(
                    order: $order,
                    to: OrderStatus::CANCELLED_BY_CUSTOMER,
                    reason: ReasonCode::INVALID_PHONE,
                    actor: $actor,
                    meta: ['call_attempt_id' => $attempt->id],
                    note: __('إلغاء الطلب: الرقم المسجل خاطئ أو لا يخص العميل. ملاحظات: :notes', ['notes' => $notes])
                );
                break;

            case CallOutcome::POSTPONED:
                OrderEvent::create([
                    'order_id' => $order->id,
                    'from_status' => (string) $order->status,
                    'to_status' => (string) $order->status,
                    'actor_type' => User::class,
                    'actor_id' => (string) $agent->id,
                    'actor_name' => $agent->name,
                    'note' => __('طلب العميل تأجيل المكالمة ومعاودة الاتصال في :i. ملاحظات: :notes', ['i' => $nextAttemptAt->format('Y-m-d H:i'), 'notes' => $notes]),
                ]);
                break;

            case CallOutcome::NO_ANSWER:
            case CallOutcome::BUSY:
            case CallOutcome::UNREACHABLE:
                if ($attemptNumber >= $maxAttempts && $settings->shouldAutoUnreachable()) {
                    $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::UNREACHABLE,
                        reason: ReasonCode::CUSTOMER_UNREACHABLE,
                        actor: $actor,
                        meta: ['call_attempt_id' => $attempt->id],
                        note: __('تعذر الوصول للعميل بعد استنفاد :max_attempts محاولات اتصال (:label).', ['max_attempts' => $maxAttempts, 'label' => $outcomeEnum->label()])
                    );
                } else {
                    OrderEvent::create([
                        'order_id' => $order->id,
                        'from_status' => (string) $order->status,
                        'to_status' => (string) $order->status,
                        'actor_type' => User::class,
                        'actor_id' => (string) $agent->id,
                        'actor_name' => $agent->name,
                        'note' => __('لم يرد العميل — المحاولة :attempt_number من :max_attempts (:label). تمت جدولة المحاولة التالية في :i.', ['attempt_number' => $attemptNumber, 'max_attempts' => $maxAttempts, 'label' => $outcomeEnum->label(), 'i' => $nextAttemptAt?->format('Y-m-d H:i')]),
                    ]);
                }
                break;
        }

        return $attempt;
    }
}
