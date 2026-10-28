<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\CallCenter\Enums\CallOutcome;
use App\Domain\CallCenter\Services\CallAttemptService;
use App\Domain\CallCenter\Services\CallCenterQueueService;
use App\Domain\CallCenter\Services\OrderModificationService;
use App\Domain\CallCenter\Settings\CallCenterSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CallAttempt;
use App\Models\User;
use App\Support\Location\Governorate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Lunar\Models\Order;

class StoreCallCenterController extends Controller
{
    public function index(Request $request, CallCenterQueueService $queueService): View
    {
        /** @var User $user */
        $user = $request->user();
        $now = Carbon::now();

        // 1. Check if user already holds an unexpired lock
        /** @var Order|null $activeOrder */
        $activeOrder = Order::query()
            ->when($user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
            ->where('assigned_to', $user->id)
            ->where('locked_until', '>', $now)
            ->with(['lines.purchasable', 'addresses'])
            ->first();

        $recentAttempts = [];
        if ($activeOrder instanceof Order) {
            $recentAttempts = CallAttempt::query()
                ->where('order_id', $activeOrder->id)
                ->with('agent')
                ->orderByDesc('created_at')
                ->get();
        }

        $stats = $queueService->getQueueStats($user->tenant_id ? (string) $user->tenant_id : null);
        $settings = CallCenterSettings::load();
        $governorates = Governorate::query()->where('is_active', true)->orderBy('name_ar')->get();

        return view('store.call-center.index', [
            'activeOrder' => $activeOrder,
            'recentAttempts' => $recentAttempts,
            'stats' => $stats,
            'settings' => $settings,
            'governorates' => $governorates,
            'outcomes' => CallOutcome::cases(),
        ]);
    }

    public function claim(Request $request, CallCenterQueueService $queueService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = $queueService->claimNextOrder($user);

        if (! $order instanceof Order) {
            return redirect()->route('store.call-center.index')
                ->with('info', 'لا توجد طلبات بانتظار التأكيد حالياً في طابور الاتصال.');
        }

        return redirect()->route('store.call-center.index')
            ->with('success', "تم سحب الطلب #{$order->id} للاتصال وتأكيده بنجاح.");
    }

    public function release(Request $request, Order $order, CallCenterQueueService $queueService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $queueService->releaseOrder($order, $user);

        return redirect()->route('store.call-center.index')
            ->with('info', "تم إلغاء حجز الطلب #{$order->id} وإعادته إلى طابور التأكيد.");
    }

    public function attempt(Request $request, Order $order, CallAttemptService $attemptService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'outcome' => ['required', 'string', Rule::in(array_column(CallOutcome::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'scheduled_callback_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'recording_url' => ['nullable', 'url', 'max:500'],
        ]);

        /** @var array{outcome: string, notes?: string|null, scheduled_callback_at?: string|null, duration_seconds?: int|null, recording_url?: string|null} $validated */
        $attempt = $attemptService->recordAttempt($order, $user, $validated);

        $outcomeEnum = $attempt->getOutcomeEnum();
        $outcomeLabel = $outcomeEnum instanceof CallOutcome ? $outcomeEnum->label() : $attempt->outcome;

        return redirect()->route('store.call-center.index')
            ->with('success', "تم تسجيل نتيجة المكالمة: {$outcomeLabel} بنجاح.");
    }

    public function modify(Request $request, Order $order, OrderModificationService $modificationService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'selling_price_minor' => ['nullable', 'integer', 'min:0'],
            'governorate_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string', 'max:100'],
            'line_one' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'customer_name' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $modificationService->modifyOrder($order, $validated, $user);

        $newProfitPounds = number_format($result['new_profit_minor'] / 100, 2);

        return redirect()->back()
            ->with('success', "تم تعديل بيانات الطلب وتحديث الحسابات. عمولة المسوق المحدثة: {$newProfitPounds} ج.م");
    }

    public function performance(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $totalAttempts = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        $confirmedCount = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('outcome', CallOutcome::CONFIRMED->value)
            ->count();

        $cancelledCount = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('outcome', [CallOutcome::CUSTOMER_CANCELLED->value, CallOutcome::WRONG_NUMBER->value])
            ->count();

        $unreachableCount = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('outcome', CallOutcome::UNREACHABLE->value)
            ->count();

        $closedCount = $confirmedCount + $cancelledCount + $unreachableCount;
        $confirmationRate = $closedCount > 0 ? round(($confirmedCount / $closedCount) * 100, 1) : 0.0;

        $avgDuration = (int) CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        // QA Sampling: Recent 20 calls with recordings or notes
        $qaCalls = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['agent', 'order'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('store.call-center.performance', [
            'totalAttempts' => $totalAttempts,
            'confirmedCount' => $confirmedCount,
            'cancelledCount' => $cancelledCount,
            'unreachableCount' => $unreachableCount,
            'confirmationRate' => $confirmationRate,
            'avgDuration' => $avgDuration,
            'qaCalls' => $qaCalls,
        ]);
    }
}
