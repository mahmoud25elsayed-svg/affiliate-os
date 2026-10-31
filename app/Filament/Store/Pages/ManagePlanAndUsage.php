<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\Billing\Features;
use App\Support\Billing\Limits;
use App\Support\Billing\Services\SubscriptionService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;

class ManagePlanAndUsage extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.store.pages.manage-plan-and-usage';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationLabel(): string
    {

        return __('الخطة والاستخدام');

    }

    public function getTitle(): string|Htmlable
    {
        return __('الخطة والاستخدام');
    }

    protected static ?int $navigationSort = 10;

    public ?Tenant $storeTenant = null;

    public ?Subscription $subscription = null;

    public ?Plan $currentPlan = null;

    /** @var Collection<int, Plan> */
    public Collection $availablePlans;

    /** @var Collection<int, SubscriptionInvoice> */
    public Collection $invoices;

    /** @var array<string, array{label: string, used: int, max: int, percentage: int}> */
    public array $usageStats = [];

    /** @var array<string, array{label: string, enabled: bool}> */
    public array $featureStats = [];

    // Modal state
    public bool $showRenewalModal = false;

    public int $selected_plan_id = 0;

    public string $selected_cycle = 'monthly';

    public string $payment_method = 'manual_instapay';

    public string $payment_reference = '';

    public string $notes = '';

    /** @var mixed */
    public $receipt_file;

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->storeTenant = Features::resolveTenant();

        if ($this->storeTenant) {
            $this->subscription = $this->storeTenant->subscription;
            $this->currentPlan = $this->subscription?->plan;
            $this->invoices = $this->subscription ? $this->subscription->invoices : collect();

            if ($this->currentPlan) {
                $this->selected_plan_id = $this->currentPlan->id;
                $this->selected_cycle = $this->subscription ? $this->subscription->billing_cycle : 'monthly';
            }
        } else {
            $this->invoices = collect();
        }

        $this->availablePlans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        $this->calculateStats();
    }

    protected function calculateStats(): void
    {
        if (! $this->storeTenant || ! $this->currentPlan) {
            return;
        }

        $limitsConfig = [
            'products' => __('المنتجات'),
            'monthly_orders' => __('الطلبات الشهرية'),
            'affiliates' => __('المسوقين (الأفلييت)'),
            'staff' => __('فريق العمل (الموظفين)'),
            'storage_mb' => __('المساحة التخزينية (ميجابايت)'),
        ];

        $this->usageStats = [];
        foreach ($limitsConfig as $key => $label) {
            $used = Limits::usage($key, $this->storeTenant);
            $max = Limits::get($key, $this->storeTenant);
            $percentage = $max > 0 ? min(100, (int) round(($used / $max) * 100)) : ($max === -1 ? 0 : 100);

            $this->usageStats[$key] = [
                'label' => $label,
                'used' => $used,
                'max' => $max,
                'percentage' => $percentage,
            ];
        }

        $featuresConfig = [
            'affiliate' => __('نظام المسوقين والعمولات'),
            'call_center' => __('مركز الاتصال والتأكيد'),
            'courier' => __('ربط شركات الشحن والمزامنة'),
            'cod_reconciliation' => __('التسوية المالية لـ COD'),
            'multi_vendor' => __('متعدد التجار والموردين'),
            'custom_domain' => __('ربط النطاق المخصص'),
            'api' => __('واجهات برمجية API'),
            'ai' => __('مساعد الذكاء الاصطناعي الذكي'),
        ];

        $this->featureStats = [];
        foreach ($featuresConfig as $key => $label) {
            $this->featureStats[$key] = [
                'label' => $label,
                'enabled' => $this->currentPlan->hasFeature($key),
            ];
        }
    }

    public function openRenewalModal(?int $planId = null): void
    {
        if ($planId) {
            $this->selected_plan_id = $planId;
        } elseif ($this->currentPlan) {
            $this->selected_plan_id = $this->currentPlan->id;
        }

        $this->showRenewalModal = true;
    }

    public function closeRenewalModal(): void
    {
        $this->showRenewalModal = false;
        $this->receipt_file = null;
        $this->payment_reference = '';
        $this->notes = '';
    }

    public function submitRenewal(SubscriptionService $service): void
    {
        $this->validate([
            'selected_plan_id' => ['required', 'exists:plans,id'],
            'selected_cycle' => ['required', 'in:monthly,yearly'],
            'payment_method' => ['required', 'string'],
            'payment_reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'receipt_file' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf,webp', 'max:10240'], // BL-07: validate file types
        ]);

        if (! $this->subscription) {
            Notification::make()->title(__('لا يوجد اشتراك متاح'))->danger()->send();

            return;
        }

        /** @var Plan $plan */
        $plan = Plan::findOrFail($this->selected_plan_id);

        $receiptPath = null;
        if ($this->receipt_file) {
            $receiptPath = $this->receipt_file->store('receipts', 'local'); // BL-07: store on secure local disk
        }

        $user = auth()->user();

        $renewalData = [
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'notes' => $this->notes,
        ];

        if (is_string($receiptPath)) {
            $renewalData['receipt_path'] = $receiptPath;
        }

        if ($user) {
            $renewalData['actor_id'] = (string) $user->getAuthIdentifier();
        }

        $service->requestManualRenewal($this->subscription, $plan, $this->selected_cycle, $renewalData);

        $this->closeRenewalModal();
        $this->loadData();

        Notification::make()
            ->title(__('تم إرسال طلب التجديد والإيصال بنجاح'))
            ->body(__('سيقوم فريق المنصة بمراجعة التحويل وتفعيل الاشتراك فورًا.'))
            ->success()
            ->send();
    }
}
