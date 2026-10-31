<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Tracking\Services\AdTrackingManager;
use App\Domain\Tracking\Services\MetaConversionsApiService;
use App\Domain\Tracking\Services\TikTokEventsApiService;
use App\Domain\Tracking\Settings\AdTrackingSettings;
use App\Http\Controllers\Controller;
use App\Models\AdConversionEvent;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorePixelTrackingController extends Controller
{
    public function __construct(
        protected AdTrackingManager $trackingManager
    ) {}

    public function index(): View
    {
        $settings = AdTrackingSettings::load();

        $events = AdConversionEvent::query()
            ->with(['order'])
            ->latest()
            ->paginate(20);

        $totalSent = AdConversionEvent::where('status', AdConversionEvent::STATUS_SENT)->count();
        $totalFailed = AdConversionEvent::where('status', AdConversionEvent::STATUS_FAILED)->count();
        $deliveredEventsCount = AdConversionEvent::where('event_name', AdConversionEvent::EVENT_DELIVERED)->count();

        return view('store.pixels.index', [
            'settings' => $settings,
            'events' => $events,
            'totalSent' => $totalSent,
            'totalFailed' => $totalFailed,
            'deliveredEventsCount' => $deliveredEventsCount,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'meta_pixel_enabled' => ['nullable', 'boolean'],
            'meta_pixel_id' => ['nullable', 'string', 'max:50'],
            'meta_conversions_api_token' => ['nullable', 'string', 'max:500'],
            'meta_test_event_code' => ['nullable', 'string', 'max:50'],
            'tiktok_pixel_enabled' => ['nullable', 'boolean'],
            'tiktok_pixel_id' => ['nullable', 'string', 'max:50'],
            'tiktok_events_api_token' => ['nullable', 'string', 'max:500'],
            'tiktok_test_event_code' => ['nullable', 'string', 'max:50'],
            'track_delivered_event' => ['nullable', 'boolean'],
        ]);

        $settings = AdTrackingSettings::load();
        $settings->meta_pixel_enabled = $request->boolean('meta_pixel_enabled');
        $settings->meta_pixel_id = ! empty($validated['meta_pixel_id']) ? trim((string) $validated['meta_pixel_id']) : null;
        $settings->meta_conversions_api_token = ! empty($validated['meta_conversions_api_token']) ? trim((string) $validated['meta_conversions_api_token']) : null;
        $settings->meta_test_event_code = ! empty($validated['meta_test_event_code']) ? trim((string) $validated['meta_test_event_code']) : null;

        $settings->tiktok_pixel_enabled = $request->boolean('tiktok_pixel_enabled');
        $settings->tiktok_pixel_id = ! empty($validated['tiktok_pixel_id']) ? trim((string) $validated['tiktok_pixel_id']) : null;
        $settings->tiktok_events_api_token = ! empty($validated['tiktok_events_api_token']) ? trim((string) $validated['tiktok_events_api_token']) : null;
        $settings->tiktok_test_event_code = ! empty($validated['tiktok_test_event_code']) ? trim((string) $validated['tiktok_test_event_code']) : null;

        $settings->track_delivered_event = $request->boolean('track_delivered_event');
        $settings->save();

        return redirect()->route('store.pixels.index')
            ->with('success', 'تم حفظ إعدادات بكسلات التتبع والـ Conversions API بنجاح.');
    }

    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:meta,tiktok'],
        ]);

        $settings = AdTrackingSettings::load();
        $testEventId = 'test_evt_'.uniqid();
        $tenantId = (string) tenant('id');

        $sampleUserData = [
            'email' => 'test-customer@example.com',
            'phone' => '+201012345678',
            'first_name' => 'تجربة',
            'city' => 'القاهرة',
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
        ];

        $sampleCustomData = [
            'currency' => Money::defaultCurrency(),
            'value' => 299.00,
            'test' => true,
        ];

        if ($validated['platform'] === 'meta') {
            if (! $settings->isMetaCapiActive()) {
                return redirect()->route('store.pixels.index')
                    ->with('error', 'يرجى تفعيل Meta Pixel وإدخال Access Token أولاً.');
            }

            app(MetaConversionsApiService::class)->sendEvent(
                tenantId: $tenantId,
                pixelId: (string) $settings->meta_pixel_id,
                accessToken: (string) $settings->meta_conversions_api_token,
                eventName: 'TestEvent',
                eventId: $testEventId,
                userData: $sampleUserData,
                customData: $sampleCustomData,
                testEventCode: $settings->meta_test_event_code,
            );
        } elseif ($validated['platform'] === 'tiktok') {
            if (! $settings->isTikTokApiActive()) {
                return redirect()->route('store.pixels.index')
                    ->with('error', 'يرجى تفعيل TikTok Pixel وإدخال Access Token أولاً.');
            }

            app(TikTokEventsApiService::class)->sendEvent(
                tenantId: $tenantId,
                pixelId: (string) $settings->tiktok_pixel_id,
                accessToken: (string) $settings->tiktok_events_api_token,
                eventName: 'TestEvent',
                eventId: $testEventId,
                userData: $sampleUserData,
                customData: $sampleCustomData,
                testEventCode: $settings->tiktok_test_event_code,
            );
        }

        return redirect()->route('store.pixels.index')
            ->with('success', "تم إرسال حدث اختباري بنجاح لمنصة {$validated['platform']}. راجع جدول التشخيص بالأسفل.");
    }
}
