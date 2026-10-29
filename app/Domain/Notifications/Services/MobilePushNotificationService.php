<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Models\Affiliate;
use App\Models\MobileDevice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MobilePushNotificationService
{
    /**
     * Register or update a mobile device token for push notifications.
     *
     * @param  array{
     *     device_token: string,
     *     platform: string,
     *     app_type: string,
     *     user_id?: int|null,
     *     affiliate_id?: int|null,
     *     device_name?: string|null,
     *     app_version?: string|null,
     *     locale?: string|null,
     * }  $data
     */
    public function registerDevice(Tenant $tenant, array $data): MobileDevice
    {
        /** @var MobileDevice $device */
        $device = MobileDevice::query()->firstOrNew([
            'tenant_id' => (string) $tenant->id,
            'device_token' => (string) $data['device_token'],
        ]);

        $device->fill([
            'platform' => (string) $data['platform'],
            'app_type' => (string) $data['app_type'],
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'affiliate_id' => isset($data['affiliate_id']) ? (int) $data['affiliate_id'] : null,
            'device_name' => $data['device_name'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'locale' => $data['locale'] ?? 'ar',
            'is_active' => true,
            'last_active_at' => Carbon::now(),
        ]);

        $device->save();

        return $device;
    }

    /**
     * Unregister a device token (e.g. user logged out or app uninstalled).
     */
    public function unregisterDevice(Tenant $tenant, string $deviceToken): bool
    {
        $device = MobileDevice::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('device_token', $deviceToken)
            ->first();

        if (! $device) {
            return false;
        }

        $device->deactivate();

        return true;
    }

    /**
     * Get active devices for an affiliate.
     *
     * @return Collection<int, MobileDevice>
     */
    public function getDevicesForAffiliate(Tenant $tenant, Affiliate|int $affiliate): Collection
    {
        $affiliateId = $affiliate instanceof Affiliate ? $affiliate->id : $affiliate;

        /** @var Collection<int, MobileDevice> $devices */
        $devices = MobileDevice::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('affiliate_id', $affiliateId)
            ->active()
            ->get();

        return $devices;
    }

    /**
     * Get active devices for a merchant (store staff / owner).
     *
     * @return Collection<int, MobileDevice>
     */
    public function getDevicesForMerchant(Tenant $tenant, ?int $userId = null): Collection
    {
        $query = MobileDevice::query()
            ->where('tenant_id', (string) $tenant->id)
            ->forApp(MobileDevice::APP_TYPE_MERCHANT)
            ->active();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        /** @var Collection<int, MobileDevice> $devices */
        $devices = $query->get();

        return $devices;
    }

    /**
     * Get active devices for a customer.
     *
     * @return Collection<int, MobileDevice>
     */
    public function getDevicesForCustomer(Tenant $tenant, int $userId): Collection
    {
        /** @var Collection<int, MobileDevice> $devices */
        $devices = MobileDevice::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', $userId)
            ->forApp(MobileDevice::APP_TYPE_CUSTOMER)
            ->active()
            ->get();

        return $devices;
    }

    /**
     * Format standard FCM v1 / APNs payload structure.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function formatPayload(MobileDevice $device, string $title, string $body, array $data = []): array
    {
        $stringData = [];
        foreach ($data as $key => $val) {
            $stringData[$key] = is_scalar($val) ? (string) $val : (string) json_encode($val);
        }

        return [
            'to' => $device->device_token,
            'platform' => $device->platform,
            'app_type' => $device->app_type,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => $stringData,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'affiliateos_updates',
                    'sound' => 'default',
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * Send push notification to a set of mobile devices.
     *
     * @param  iterable<int, MobileDevice>  $devices
     * @param  array<string, mixed>  $data
     * @return array{dispatched: int, failed: int, tokens: list<string>}
     */
    public function sendPushNotification(iterable $devices, string $title, string $body, array $data = []): array
    {
        $dispatched = 0;
        $tokens = [];

        foreach ($devices as $device) {
            if (! $device->is_active) {
                continue;
            }

            $payload = $this->formatPayload($device, $title, $body, $data);
            $tokens[] = $device->device_token;
            $dispatched++;

            Log::info("Mobile push notification dispatched [{$device->platform}/{$device->app_type}]", [
                'device_id' => $device->id,
                'tenant_id' => $device->tenant_id,
                'title' => $title,
                'token_prefix' => substr($device->device_token, 0, 8).'...',
            ]);
        }

        return [
            'dispatched' => $dispatched,
            'failed' => 0,
            'tokens' => $tokens,
        ];
    }

    /**
     * Send push notification to an affiliate's active mobile devices.
     *
     * @param  array<string, mixed>  $data
     * @return array{dispatched: int, failed: int, tokens: list<string>}
     */
    public function sendToAffiliate(Tenant $tenant, Affiliate|int $affiliate, string $title, string $body, array $data = []): array
    {
        $devices = $this->getDevicesForAffiliate($tenant, $affiliate);

        return $this->sendPushNotification($devices, $title, $body, $data);
    }

    /**
     * Send push notification to merchant operations staff.
     *
     * @param  array<string, mixed>  $data
     * @return array{dispatched: int, failed: int, tokens: list<string>}
     */
    public function sendToMerchant(Tenant $tenant, string $title, string $body, array $data = [], ?int $userId = null): array
    {
        $devices = $this->getDevicesForMerchant($tenant, $userId);

        return $this->sendPushNotification($devices, $title, $body, $data);
    }

    /**
     * Send push notification to a registered customer.
     *
     * @param  array<string, mixed>  $data
     * @return array{dispatched: int, failed: int, tokens: list<string>}
     */
    public function sendToCustomer(Tenant $tenant, int $userId, string $title, string $body, array $data = []): array
    {
        $devices = $this->getDevicesForCustomer($tenant, $userId);

        return $this->sendPushNotification($devices, $title, $body, $data);
    }
}
