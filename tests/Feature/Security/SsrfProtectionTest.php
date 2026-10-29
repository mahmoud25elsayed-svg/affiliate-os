<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Webhooks\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Rules\SafeWebhookUrl;
use App\Support\Security\Exceptions\SsrfBlockedException;
use App\Support\Security\SsrfProtection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SsrfProtectionTest extends TestCase
{
    protected function tearDown(): void
    {
        SsrfProtection::reset();
        parent::tearDown();
    }

    public function test_blocks_loopback_and_local_addresses(): void
    {
        $blockedUrls = [
            'http://127.0.0.1',
            'http://127.0.0.1:8000/admin',
            'http://localhost',
            'http://localhost:3000',
            'http://sub.localhost',
            'http://0.0.0.0',
            'http://[::1]',
            'http://[::1]:8080/metrics',
        ];

        foreach ($blockedUrls as $url) {
            $this->assertFalse(
                SsrfProtection::isSafeUrl($url),
                "Expected [{$url}] to be blocked by SSRF protection."
            );

            $this->expectException(SsrfBlockedException::class);
            SsrfProtection::assertSafeUrl($url);
        }
    }

    public function test_blocks_cloud_metadata_services(): void
    {
        $metadataUrls = [
            'http://169.254.169.254',
            'http://169.254.169.254/latest/meta-data/',
            'http://169.254.169.254/computeMetadata/v1/',
            'http://metadata.google.internal',
            'http://instance-data',
        ];

        foreach ($metadataUrls as $url) {
            $this->assertFalse(
                SsrfProtection::isSafeUrl($url),
                "Expected metadata endpoint [{$url}] to be blocked."
            );
        }
    }

    public function test_blocks_rfc_1918_private_network_addresses(): void
    {
        $privateUrls = [
            'http://10.0.0.1/status',
            'http://10.255.255.255/db',
            'http://172.16.0.10:9000',
            'http://172.31.255.1:5432',
            'http://192.168.1.1/router',
            'http://192.168.0.254:6379',
        ];

        foreach ($privateUrls as $url) {
            $this->assertFalse(
                SsrfProtection::isSafeUrl($url),
                "Expected private RFC 1918 URL [{$url}] to be blocked."
            );
        }
    }

    public function test_blocks_unsupported_or_dangerous_protocols(): void
    {
        $dangerousSchemes = [
            'file:///etc/passwd',
            'gopher://127.0.0.1:6379/_INFO',
            'dict://127.0.0.1:11211',
            'ftp://internal.server/data',
            'php://filter/read=convert.base64-encode/resource=index.php',
            'data:text/html,<script>alert(1)</script>',
        ];

        foreach ($dangerousSchemes as $url) {
            $this->assertFalse(
                SsrfProtection::isSafeUrl($url),
                "Expected dangerous scheme [{$url}] to be blocked."
            );
        }
    }

    public function test_blocks_domains_that_resolve_to_private_ips(): void
    {
        // Mock DNS resolver to simulate a domain rebinding to an internal IP
        SsrfProtection::setDnsResolver(function (string $host): array {
            if ($host === 'rebind.attacker.com') {
                return ['127.0.0.1'];
            }

            if ($host === 'safe.example.com') {
                return ['93.184.216.34']; // Public IP
            }

            return [];
        });

        $this->assertFalse(
            SsrfProtection::isSafeUrl('http://rebind.attacker.com/webhook'),
            'Domain resolving to loopback must be blocked.'
        );

        $this->assertTrue(
            SsrfProtection::isSafeUrl('https://safe.example.com/webhook'),
            'Domain resolving to public IP should be allowed.'
        );
    }

    public function test_validation_rule_rejects_unsafe_webhook_urls(): void
    {
        $rule = new SafeWebhookUrl;

        $v1 = Validator::make(['url' => 'http://169.254.169.254/latest'], ['url' => [$rule]]);
        $this->assertTrue($v1->fails(), 'Validation should fail on metadata IP');

        $v2 = Validator::make(['url' => 'http://127.0.0.1:8080/hook'], ['url' => [$rule]]);
        $this->assertTrue($v2->fails(), 'Validation should fail on loopback');

        $v3 = Validator::make(['url' => 'ftp://evil.com/hook'], ['url' => [$rule]]);
        $this->assertTrue($v3->fails(), 'Validation should fail on non-http/https URL');

        $v4 = Validator::make(['url' => 'https://example.com/webhook'], ['url' => [$rule]]);
        $this->assertFalse($v4->fails(), 'Validation should pass on public URL');
    }

    public function test_dispatch_webhook_job_blocks_ssrf_without_making_http_call(): void
    {
        Http::fake();

        $tenant = $this->ensureTenantExists('tenant_ssrf');

        /** @var WebhookSubscription $subscription */
        $subscription = WebhookSubscription::create([
            'tenant_id' => (string) $tenant->id,
            'url' => 'http://169.254.169.254/latest/meta-data',
            'secret' => 'whsec_test_secret',
            'events' => ['order.created'],
            'is_active' => true,
        ]);

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::create([
            'tenant_id' => (string) $tenant->id,
            'webhook_subscription_id' => $subscription->id,
            'event' => 'order.created',
            'payload' => ['test' => 'data'],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt' => 0,
        ]);

        $job = new DispatchWebhookJob($delivery->id);
        $job->handle();

        $delivery->refresh();
        $subscription->refresh();

        $this->assertEquals(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertEquals(400, $delivery->response_status);
        $this->assertStringContainsString('Blocked by SSRF protection', (string) $delivery->response_body);
        $this->assertEquals(1, $subscription->failure_count);

        // Crucial: assert that NO outgoing HTTP request was dispatched
        Http::assertNothingSent();
    }
}
