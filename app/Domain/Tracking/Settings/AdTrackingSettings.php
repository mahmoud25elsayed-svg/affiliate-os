<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Settings;

use App\Support\Settings\Settings;

class AdTrackingSettings extends Settings
{
    public bool $meta_pixel_enabled = false;

    public ?string $meta_pixel_id = null;

    public ?string $meta_conversions_api_token = null;

    public ?string $meta_test_event_code = null;

    public bool $tiktok_pixel_enabled = false;

    public ?string $tiktok_pixel_id = null;

    public ?string $tiktok_events_api_token = null;

    public ?string $tiktok_test_event_code = null;

    public bool $track_delivered_event = true;

    public static function group(): string
    {
        return 'ad_tracking';
    }

    public function isMetaCapiActive(): bool
    {
        return $this->meta_pixel_enabled
            && ! empty($this->meta_pixel_id)
            && ! empty($this->meta_conversions_api_token);
    }

    public function isTikTokApiActive(): bool
    {
        return $this->tiktok_pixel_enabled
            && ! empty($this->tiktok_pixel_id)
            && ! empty($this->tiktok_events_api_token);
    }
}
