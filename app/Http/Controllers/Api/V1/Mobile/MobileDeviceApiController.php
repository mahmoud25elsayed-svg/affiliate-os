<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Domain\Notifications\Services\MobilePushNotificationService;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\ApiKey;
use App\Models\MobileDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeviceApiController extends Controller
{
    public function __construct(
        protected MobilePushNotificationService $pushService,
    ) {}

    /**
     * Register or update a mobile device token for push notifications.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:'.implode(',', MobileDevice::SUPPORTED_PLATFORMS)],
            'app_type' => ['required', 'string', 'in:'.implode(',', MobileDevice::SUPPORTED_APP_TYPES)],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'max:10'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'affiliate_id' => ['nullable', 'integer', 'exists:affiliates,id'],
        ]);

        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return response()->json([
                'error' => 'Unprocessable',
                'message' => 'Unable to determine tenant context for device registration.',
            ], 422);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey) {
            if ($apiKey->owner instanceof Affiliate && empty($validated['affiliate_id'])) {
                $validated['affiliate_id'] = $apiKey->owner->id;
            } elseif ($apiKey->owner instanceof User && empty($validated['user_id'])) {
                $validated['user_id'] = $apiKey->owner->id;
            }
        }

        $device = $this->pushService->registerDevice($tenant, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Mobile device registered successfully.',
            'data' => [
                'id' => $device->id,
                'device_token' => $device->device_token,
                'platform' => $device->platform,
                'app_type' => $device->app_type,
                'user_id' => $device->user_id,
                'affiliate_id' => $device->affiliate_id,
                'is_active' => $device->is_active,
                'last_active_at' => $device->last_active_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Unregister a device token on logout or app removal.
     */
    public function unregister(Request $request, string $token): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return response()->json([
                'error' => 'Unprocessable',
                'message' => 'Unable to determine tenant context for device unregistration.',
            ], 422);
        }

        $success = $this->pushService->unregisterDevice($tenant, $token);

        return response()->json([
            'success' => $success,
            'message' => $success
                ? 'Mobile device unregistered successfully.'
                : 'Device token not found or already inactive.',
        ], 200);
    }

    /**
     * Retrieve dynamic white-label branding and capabilities configuration.
     */
    public function config(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return response()->json([
                'error' => 'Unprocessable',
                'message' => 'Unable to determine tenant context.',
            ], 422);
        }

        return response()->json([
            'tenant_id' => (string) $tenant->id,
            'store_name' => $tenant->name ?? 'AffiliateOS Store',
            'currency' => Money::defaultCurrency(),
            'locale' => 'ar',
            'branding' => [
                'primary_color' => '#10B981',
                'secondary_color' => '#0F172A',
                'font_family' => 'Cairo',
                'logo_url' => null,
            ],
            'features' => [
                'affiliate_portal' => true,
                'cod_checkout' => true,
                'order_tracking' => true,
                'biometric_auth' => true,
                'camera_kyc' => true,
            ],
            'api_version' => 'v1',
        ], 200);
    }

    /**
     * Resolve active tenant from API key or tenancy environment.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::find($apiKey->tenant_id);

            return $tenant;
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            /** @var Tenant|null $tenant */
            $tenant = tenant();

            return $tenant;
        }

        return null;
    }
}
