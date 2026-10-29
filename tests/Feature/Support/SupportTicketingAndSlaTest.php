<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Support\Services\TicketService;
use App\Models\Affiliate;
use App\Models\HelpArticle;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCannedResponse;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Tests\TestCase;

class SupportTicketingAndSlaTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected User $staffUser;

    protected Affiliate $affiliate1;

    protected Affiliate $affiliate2;

    protected Channel $channel;

    protected string $domain = 'support-store.test';

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Plan & Subscription
        $plan = Plan::create([
            'slug' => 'growth-plan',
            'name_ar' => 'باقة النمو',
            'name_en' => 'Growth Plan',
            'price_monthly' => 79900,
            'price_yearly' => 799000,
            'limits' => [],
            'features' => ['affiliate' => true],
            'is_active' => true,
        ]);

        $this->tenant = Tenant::create([
            'id' => 'support-tenant',
            'name' => 'متجر الدعم الفني',
        ]);
        $this->tenant->domains()->create(['domain' => $this->domain]);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        // 2. Staff & Owner
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'owner@support-store.test',
        ]);
        $this->staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'staff@support-store.test',
        ]);

        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->owner, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);
        TenantPermissionMatrix::assignRole($this->staffUser, TenantPermissionMatrix::ROLE_SUPPORT, $this->tenant->id);

        // 3. Affiliates
        $this->tenant->run(function () {
            $this->affiliate1 = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'مسوق أول',
                'phone' => '+201011112222',
                'email' => 'affiliate1@store.test',
                'password' => bcrypt('password'),
                'code' => 'AFF1',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            $this->affiliate2 = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'مسوق ثان',
                'phone' => '+201033334444',
                'email' => 'affiliate2@store.test',
                'password' => bcrypt('password'),
                'code' => 'AFF2',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            Currency::firstOrCreate(['code' => 'EGP'], [
                'name' => 'Egyptian Pound',
                'exchange_rate' => 1.0,
                'decimal_places' => 2,
                'enabled' => true,
                'default' => true,
            ]);

            $this->channel = Channel::firstOrCreate(['handle' => 'default'], [
                'name' => 'Default Channel',
                'default' => true,
                'url' => 'http://localhost',
            ]);
        });
    }

    public function test_affiliate_can_open_store_ticket_and_link_to_order(): void
    {
        $this->actingAs($this->affiliate1, 'affiliate');

        $order = null;
        $this->tenant->run(function () use (&$order) {
            OrderTransitionService::$isTransitioning = true;

            try {
                $order = Order::create([
                    'tenant_id' => $this->tenant->id,
                    'channel_id' => $this->channel->id,
                    'reference' => 'ORD-SPT-100',
                    'customer_reference' => 'ORD-SPT-100',
                    'status' => 'placed',
                    'sub_total' => 50000,
                    'total' => 50000,
                    'discount_total' => 0,
                    'shipping_total' => 0,
                    'tax_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'currency_code' => 'EGP',
                    'payment_method' => 'cod',
                ]);
            } finally {
                OrderTransitionService::$isTransitioning = false;
            }
        });

        $res = $this->post("http://{$this->domain}/partners/support", [
            'subject' => 'استفسار عن تأخر تسليم طلب لعميل',
            'department' => 'shipping',
            'priority' => 'high',
            'order_id' => $order->id,
            'message' => 'العميل يسأل متى ستصل الشحنة؟ يرجى الإفادة.',
        ]);

        $res->assertSessionHas('success');

        $ticket = Ticket::where('tenant_id', $this->tenant->id)
            ->where('affiliate_id', $this->affiliate1->id)
            ->first();

        $this->assertNotNull($ticket);
        $this->assertSame('store', $ticket->level);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame($order->id, $ticket->order_id);
        $this->assertStringStartsWith('TCK-', $ticket->ticket_number);

        // Verify initial message was recorded
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'sender_type' => 'affiliate',
            'is_internal_note' => false,
        ]);
    }

    public function test_merchant_can_open_platform_ticket_with_platform_admin(): void
    {
        $this->actingAs($this->owner);

        $res = $this->post("http://{$this->domain}/store/support/platform-ticket", [
            'subject' => 'طلب مساعدة في ربط DNS الدومين المخصص',
            'department' => 'technical',
            'priority' => 'urgent',
            'message' => 'أرغب في التأكد من تفعيل شهادة SSL على استضافة Hostinger.',
        ]);

        $res->assertSessionHas('success');

        $ticket = Ticket::where('tenant_id', $this->tenant->id)
            ->where('level', Ticket::LEVEL_PLATFORM)
            ->first();

        $this->assertNotNull($ticket);
        $this->assertSame('urgent', $ticket->priority);
        $this->assertStringStartsWith('PLT-', $ticket->ticket_number);
    }

    public function test_sla_breach_detection_with_travel_to(): void
    {
        $ticketService = app(TicketService::class);

        // Create an urgent ticket (SLA = 2 hours)
        $ticket = $ticketService->createStoreTicket(
            data: [
                'subject' => 'مشكلة حرجة في عمولة كود الخصم',
                'message' => 'العمولات لا تسجل في المحفظة',
                'priority' => Ticket::PRIORITY_URGENT,
            ],
            tenant: $this->tenant,
            affiliate: $this->affiliate1
        );

        $this->assertFalse($ticket->isSlaBreached());
        $this->assertSame(0, Ticket::breachedSla()->count());

        // Fast-forward 3 hours (past 2 hours SLA)
        $this->travelTo(now()->addHours(3));

        $this->assertTrue($ticket->isSlaBreached());
        $this->assertSame(1, Ticket::breachedSla()->count());

        // Resolve ticket -> SLA should no longer be breached
        $ticketService->updateStatus($ticket, Ticket::STATUS_RESOLVED);
        $this->assertFalse($ticket->isSlaBreached());
        $this->assertSame(0, Ticket::breachedSla()->count());
    }

    public function test_internal_notes_are_strictly_hidden_from_affiliate(): void
    {
        $ticketService = app(TicketService::class);

        $ticket = $ticketService->createStoreTicket(
            data: [
                'subject' => 'نزاع حول طلب مرتجع',
                'message' => 'الطلب وصل للعميل فلماذا تم اعتباره مرتجع؟',
                'priority' => Ticket::PRIORITY_MEDIUM,
            ],
            tenant: $this->tenant,
            affiliate: $this->affiliate1
        );

        // Staff adds an internal note (for team only)
        $ticketService->addReply(
            ticket: $ticket,
            message: 'ملاحظة خاصة: العميل رفض الاستلام وتم التواصل معه وقال إن المنتج غير مطابق. لا تقبل النزاع.',
            senderType: TicketMessage::SENDER_STAFF,
            user: $this->staffUser,
            isInternalNote: true
        );

        // Staff adds a public reply
        $ticketService->addReply(
            ticket: $ticket,
            message: 'أهلاً بك، تواصلنا مع شركة الشحن وجاري التحقق من سبب الارتجاع.',
            senderType: TicketMessage::SENDER_STAFF,
            user: $this->staffUser,
            isInternalNote: false
        );

        // 1. Staff visits ticket in store dashboard -> CAN see internal note
        $this->actingAs($this->staffUser);
        $storeRes = $this->get("http://{$this->domain}/store/support/{$ticket->id}");
        $storeRes->assertStatus(200);
        $storeRes->assertSee('ملاحظة داخلية (Staff Only)');
        $storeRes->assertSee('ملاحظة خاصة: العميل رفض الاستلام');

        // 2. Affiliate visits ticket -> MUST NOT see internal note!
        $this->actingAs($this->affiliate1, 'affiliate');
        $affiliateRes = $this->get("http://{$this->domain}/partners/support/{$ticket->id}");
        $affiliateRes->assertStatus(200);
        $affiliateRes->assertSee('أهلاً بك، تواصلنا مع شركة الشحن');
        $affiliateRes->assertDontSee('ملاحظة خاصة: العميل رفض الاستلام');
        $affiliateRes->assertDontSee('Staff Only');
    }

    public function test_first_response_time_and_metrics_calculation(): void
    {
        $ticketService = app(TicketService::class);

        $ticket = $ticketService->createStoreTicket(
            data: [
                'subject' => 'استفسار عن بونص المسوقين',
                'message' => 'متى يتم تفعيل بونص الـ 100 طلب؟',
                'priority' => Ticket::PRIORITY_LOW,
            ],
            tenant: $this->tenant,
            affiliate: $this->affiliate1
        );

        $this->assertNull($ticket->first_response_at);

        // Travel 45 minutes ahead
        $this->travelTo(now()->addMinutes(45));

        // Staff replies
        $ticketService->addReply(
            ticket: $ticket,
            message: 'أهلاً بك، يتم تفعيل البونص تلقائياً أول كل شهر ميلادي.',
            senderType: TicketMessage::SENDER_STAFF,
            user: $this->staffUser,
            isInternalNote: false
        );

        $ticket->refresh();
        $this->assertNotNull($ticket->first_response_at);
        $this->assertSame(45, $ticket->getFirstResponseTimeMinutes());

        // Travel 15 more minutes and resolve (total 60 mins)
        $this->travelTo(now()->addMinutes(15));
        $ticketService->updateStatus($ticket, Ticket::STATUS_RESOLVED);

        $ticket->refresh();
        $this->assertSame(60, $ticket->getResolutionTimeMinutes());

        // Check aggregate metrics
        $metrics = $ticketService->getMetrics($this->tenant, Ticket::LEVEL_STORE);
        $this->assertSame(45, $metrics['avg_first_response_minutes']);
        $this->assertSame(60, $metrics['avg_resolution_minutes']);
        $this->assertSame(1, $metrics['resolved_today_count']);
    }

    public function test_affiliate_cannot_access_other_affiliates_tickets(): void
    {
        $ticketService = app(TicketService::class);

        // Ticket belongs to Affiliate 1
        $ticket1 = $ticketService->createStoreTicket(
            data: [
                'subject' => 'تذكرة خاصة بالمسوق 1',
                'message' => 'بيانات سرية للطلب',
            ],
            tenant: $this->tenant,
            affiliate: $this->affiliate1
        );

        // Affiliate 2 tries to view ticket 1
        $this->actingAs($this->affiliate2, 'affiliate');
        $res = $this->get("http://{$this->domain}/partners/support/{$ticket1->id}");
        $res->assertStatus(404);
    }

    public function test_canned_responses_render_placeholders(): void
    {
        $canned = TicketCannedResponse::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'اعتذار عن تأخير الشحن',
            'shortcut' => '/delay',
            'content' => 'مرحباً {{customer_name}}، نعتذر عن التأخير بخصوص تذكرتك {{ticket_number}}. تم التواصل مع شركة الشحن لإعطاء الأولوية.',
        ]);

        $rendered = $canned->render([
            'customer_name' => 'محمد السيد',
            'ticket_number' => 'TCK-2026-9999',
        ]);

        $this->assertStringContainsString('مرحباً محمد السيد', $rendered);
        $this->assertStringContainsString('تذكرتك TCK-2026-9999', $rendered);
    }

    public function test_help_center_faq_articles_for_storefront_and_affiliates(): void
    {
        // 1. Create Public customer article
        HelpArticle::create([
            'tenant_id' => $this->tenant->id,
            'audience' => HelpArticle::AUDIENCE_PUBLIC,
            'category' => 'الشحن والتوصيل',
            'question' => 'ما هي مدة توصيل الطلبات في القاهرة والجيزة؟',
            'answer' => 'مدة التوصيل تستغرق من 24 إلى 48 ساعة كحد أقصى.',
            'sort_order' => 1,
            'is_published' => true,
        ]);

        // 2. Create Affiliate article
        HelpArticle::create([
            'tenant_id' => $this->tenant->id,
            'audience' => HelpArticle::AUDIENCE_AFFILIATES,
            'category' => 'العمولات والسحب',
            'question' => 'متى تصبح العمولة قابلة للسحب؟',
            'answer' => 'تتحول العمولة إلى الرصيد المتاح للسحب فور تحصيل قيمة الطلب من العميل (حالة DELIVERED).',
            'sort_order' => 1,
            'is_published' => true,
        ]);

        // 3. Customer visits storefront FAQ
        $pubRes = $this->get("http://{$this->domain}/help");
        $pubRes->assertStatus(200);
        $pubRes->assertSee('ما هي مدة توصيل الطلبات في القاهرة والجيزة؟');
        $pubRes->assertDontSee('متى تصبح العمولة قابلة للسحب؟');

        // 4. Affiliate visits partner FAQ
        $this->actingAs($this->affiliate1, 'affiliate');
        $affRes = $this->get("http://{$this->domain}/partners/help");
        $affRes->assertStatus(200);
        $affRes->assertSee('متى تصبح العمولة قابلة للسحب؟');
        $affRes->assertDontSee('ما هي مدة توصيل الطلبات في القاهرة والجيزة؟');
    }
}
