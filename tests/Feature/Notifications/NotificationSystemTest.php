<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\Enums\NotificationChannel as ChannelEnum;
use App\Domain\Notifications\Jobs\DispatchNotificationJob;
use App\Domain\Notifications\Services\NotificationManager;
use App\Domain\Notifications\Services\TemplateEngine;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Promotions\Events\AbandonedCartDetected;
use App\Models\AbandonedCart;
use App\Models\InAppNotification;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\OrderEvent;
use App\Models\Tenant;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected NotificationManager $notificationManager;

    protected TemplateEngine $templateEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('notifystore');

        $this->notificationManager = app(NotificationManager::class);
        $this->templateEngine = app(TemplateEngine::class);
    }

    public function test_can_send_notification_via_fake_driver_and_logs_sent_status(): void
    {
        $this->tenant->run(function (): void {
            $fake = $this->notificationManager->fake();

            $log = $this->notificationManager->send(
                channel: ChannelEnum::SMS,
                recipient: '01012345678',
                eventType: 'welcome',
                data: ['customer_name' => 'أحمد محمود', 'store_name' => 'متجر التجربة']
            );

            expect($log)->toBeInstanceOf(NotificationLog::class)
                ->and($log->status)->toBe('sent')
                ->and($log->channel)->toBe('sms')
                ->and($log->recipient)->toBe('01012345678')
                ->and($log->cost_minor)->toBe(25)
                ->and($log->content)->toContain('أحمد محمود')
                ->and($fake->getSentCount())->toBe(1);

            $fake->assertSent(function (NotificationMessage $msg): bool {
                return $msg->recipient === '01012345678' && str_contains($msg->content, 'أحمد محمود');
            });
        });
    }

    public function test_gracefully_handles_driver_exception_without_throwing_non_blocking(): void
    {
        $this->tenant->run(function (): void {
            $fake = $this->notificationManager->fake();
            $fake->shouldFail(true, 'SMS Gateway Timeout 504');

            // Must NOT throw exception
            $log = $this->notificationManager->send(
                channel: ChannelEnum::SMS,
                recipient: '01123456789',
                eventType: 'order_confirmed',
                data: ['order_number' => 'ORD-999']
            );

            expect($log)->toBeInstanceOf(NotificationLog::class)
                ->and($log->status)->toBe('failed')
                ->and($log->error_message)->toContain('SMS Gateway Timeout 504')
                ->and($log->sent_at)->toBeNull();
        });
    }

    public function test_daily_spend_limit_blocks_exceeded_channel(): void
    {
        $this->tenant->run(function (): void {
            $this->notificationManager->fake();

            $channel = NotificationChannel::create([
                'tenant_id' => $this->tenant->id,
                'channel' => 'sms',
                'provider' => 'fake',
                'is_active' => true,
                'is_default' => true,
                'daily_cost_limit_minor' => 40, // 0.40 EGP
                'current_day_cost_minor' => 0,
                'last_cost_reset_at' => Carbon::today(),
            ]);

            // First send costs 25 minor -> 25 <= 40 => succeeds
            $log1 = $this->notificationManager->send(
                channel: ChannelEnum::SMS,
                recipient: '01011111111',
                eventType: 'welcome',
                data: ['customer_name' => 'عميل 1']
            );
            expect($log1->status)->toBe('sent');

            $channel->refresh();
            expect($channel->current_day_cost_minor)->toBe(25);

            // Second send: estimated cost 30 minor -> 25 + 30 = 55 > 40 => BLOCKED
            $log2 = $this->notificationManager->send(
                channel: ChannelEnum::SMS,
                recipient: '01022222222',
                eventType: 'welcome',
                data: ['customer_name' => 'عميل 2']
            );

            expect($log2->status)->toBe('failed')
                ->and($log2->error_message)->toContain('Daily spend limit exceeded');
        });
    }

    public function test_template_engine_safe_interpolation_and_default_templates(): void
    {
        $template = 'مرحباً {customer_name}، كود التفعيل هو {otp_code} لمتجر {store_name}.';
        $data = [
            'customer_name' => 'سارة علي',
            'otp_code' => '9842',
            'store_name' => 'بريق الأناقة',
        ];

        $rendered = $this->templateEngine->interpolate($template, $data);
        expect($rendered)->toBe('مرحباً سارة علي، كود التفعيل هو 9842 لمتجر بريق الأناقة.');

        $defaults = $this->templateEngine->getDefaultTemplates();
        expect($defaults)->toHaveKeys(['order_confirmed', 'order_shipped', 'order_delivered', 'cart_abandoned', 'otp_verification', 'welcome']);
    }

    public function test_custom_template_rendering_by_tenant_and_locale(): void
    {
        $this->tenant->run(function (): void {
            $this->notificationManager->fake();

            NotificationTemplate::create([
                'tenant_id' => $this->tenant->id,
                'event_type' => 'order_confirmed',
                'channel' => 'sms',
                'locale' => 'ar',
                'title' => 'قالب مخصص لتأكيد الطلب',
                'subject' => 'تأكيد طلبك',
                'content' => 'يا {customer_name}! طلبك {order_number} قيد التحضير بقيمة {order_total}.',
                'is_active' => true,
            ]);

            $log = $this->notificationManager->send(
                channel: ChannelEnum::SMS,
                recipient: '01234567890',
                eventType: 'order_confirmed',
                data: [
                    'customer_name' => 'كريم',
                    'order_number' => 'ORD-5555',
                    'order_total' => '750 ج.م',
                ]
            );

            expect($log->content)->toBe('يا كريم! طلبك ORD-5555 قيد التحضير بقيمة 750 ج.م.');
        });
    }

    public function test_order_confirmed_event_triggers_notification_listener(): void
    {
        $this->tenant->run(function (): void {
            $fake = $this->notificationManager->fake();

            $currency = Currency::getDefault();
            if (! $currency) {
                $currency = Currency::create([
                    'code' => 'EGP',
                    'name' => 'Egyptian Pound',
                    'exchange_rate' => 1,
                    'decimal_places' => 2,
                    'enabled' => true,
                    'default' => true,
                ]);
            }

            $channel = Channel::firstOrCreate(
                ['handle' => 'web'],
                ['name' => 'Web', 'default' => true, 'url' => 'http://localhost']
            );

            $order = Order::create([
                'customer_id' => null,
                'channel_id' => $channel->id,
                'status' => 'pending_confirmation',
                'sub_total' => 30000,
                'discount_total' => 0,
                'shipping_total' => 5000,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'total' => 35000,
                'reference' => 'ORD-1010',
                'currency_code' => $currency->code,
                'compare_currency_code' => $currency->code,
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'طارق',
                'last_name' => 'جمال',
                'contact_phone' => '01099887766',
                'contact_email' => 'tarek@example.com',
                'line_one' => 'شارع التحرير',
                'city' => 'القاهرة',
            ]);

            $orderEvent = OrderEvent::create([
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'from_status' => 'submitted',
                'to_status' => 'confirmed',
                'actor_type' => 'system',
                'actor_id' => 'system',
            ]);

            // Dispatch event
            OrderConfirmed::dispatch($order, $orderEvent);

            // Fake driver should have received notification
            expect($fake->getSentCount())->toBeGreaterThanOrEqual(1);

            $fake->assertSent(function (NotificationMessage $msg): bool {
                return $msg->recipient === '01099887766' && str_contains($msg->content, 'ORD-1010');
            });
        });
    }

    public function test_abandoned_cart_detected_event_triggers_recovery_notification(): void
    {
        $this->tenant->run(function (): void {
            $fake = $this->notificationManager->fake();

            $cart = AbandonedCart::create([
                'tenant_id' => $this->tenant->id,
                'session_id' => 'sess_abandon_123',
                'phone_e164' => '01511223344',
                'email' => 'cart@example.com',
                'customer_name' => 'مصطفى كامل',
                'cart_data' => ['items' => ['SKU-1', 'SKU-2']],
                'subtotal_minor' => 120000,
                'items_count' => 2,
                'last_active_at' => Carbon::now()->subMinutes(45),
                'recovery_token' => 'token_xyz_999',
            ]);

            AbandonedCartDetected::dispatch($cart);

            expect($fake->getSentCount())->toBeGreaterThanOrEqual(1);

            $fake->assertSent(function (NotificationMessage $msg): bool {
                return $msg->recipient === '01511223344' && str_contains($msg->content, 'token_xyz_999');
            });
        });
    }

    public function test_in_app_notification_driver_persists_and_marks_as_read(): void
    {
        $this->tenant->run(function (): void {
            $log = $this->notificationManager->send(
                channel: ChannelEnum::IN_APP,
                recipient: '42',
                eventType: 'welcome',
                data: ['customer_name' => 'محمود'],
                metadata: [
                    'user_type' => 'customer',
                    'user_id' => 42,
                    'action_url' => '/account/notifications',
                ]
            );

            expect($log->status)->toBe('sent');

            $inApp = InAppNotification::where('user_id', 42)->first();
            expect($inApp)->not->toBeNull()
                ->and($inApp?->read_at)->toBeNull()
                ->and($inApp?->action_url)->toBe('/account/notifications');

            $inApp?->markAsRead();
            $inApp?->refresh();
            expect($inApp?->read_at)->not->toBeNull();
        });
    }

    public function test_async_dispatch_job_executes_successfully(): void
    {
        $this->tenant->run(function (): void {
            $fake = $this->notificationManager->fake();

            $job = new DispatchNotificationJob(
                channel: 'sms',
                recipient: '01055554444',
                eventType: 'welcome',
                data: ['customer_name' => 'يوسف', 'store_name' => 'المتجر']
            );

            $job->handle($this->notificationManager);

            expect($fake->getSentCount())->toBe(1);
        });
    }

    public function test_tenant_isolation_for_channels_and_templates_and_logs(): void
    {
        $tenantA = $this->tenant;
        $tenantB = $this->ensureTenantExists('otherstore');

        $tenantA->run(function () use ($tenantA): void {
            NotificationChannel::create([
                'tenant_id' => $tenantA->id,
                'channel' => 'sms',
                'provider' => 'fake',
                'is_active' => true,
            ]);

            NotificationLog::create([
                'tenant_id' => $tenantA->id,
                'channel' => 'sms',
                'provider' => 'fake',
                'recipient' => '01011111111',
                'event_type' => 'welcome',
                'content' => 'Tenant A log',
                'status' => 'sent',
            ]);
        });

        $tenantB->run(function (): void {
            expect(NotificationChannel::count())->toBe(0)
                ->and(NotificationLog::count())->toBe(0);
        });
    }
}
