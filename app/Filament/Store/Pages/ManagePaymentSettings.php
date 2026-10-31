<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Models\TenantPaymentGateway;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ManagePaymentSettings extends Page
{
    protected string $view = 'filament.store.pages.manage-payment-settings';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationLabel(): string
    {

        return __('بوابات الدفع الإلكتروني');

    }

    public function getTitle(): string|Htmlable
    {
        return __('إعدادات بوابات الدفع الإلكتروني');
    }

    // Paymob
    public bool $paymob_enabled = false;

    public string $paymob_mode = 'sandbox';

    public ?string $paymob_api_key = '';

    public ?string $paymob_integration_id = '';

    public ?string $paymob_iframe_id = '';

    public ?string $paymob_hmac_secret = '';

    // Fake Gateway (Development/Sandbox)
    public bool $fake_enabled = false;

    // Fawry
    public bool $fawry_enabled = false;

    public string $fawry_mode = 'sandbox';

    public ?string $fawry_merchant_code = '';

    public ?string $fawry_security_key = '';

    // Kashier
    public bool $kashier_enabled = false;

    public string $kashier_mode = 'sandbox';

    public ?string $kashier_merchant_id = '';

    public ?string $kashier_api_key = '';

    public function mount(): void
    {
        // Paymob
        $paymob = TenantPaymentGateway::where('gateway', 'paymob')->first();
        if ($paymob) {
            $this->paymob_enabled = (bool) $paymob->is_enabled;
            $this->paymob_mode = $paymob->mode ?? 'sandbox';
            $this->paymob_api_key = (string) $paymob->getCredential('api_key', '');
            $this->paymob_integration_id = (string) $paymob->getCredential('integration_id', '');
            $this->paymob_iframe_id = (string) $paymob->getCredential('iframe_id', '');
            $this->paymob_hmac_secret = (string) $paymob->getCredential('hmac_secret', '');
        }

        // Fake
        $fake = TenantPaymentGateway::where('gateway', 'fake')->first();
        if ($fake) {
            $this->fake_enabled = (bool) $fake->is_enabled;
        }

        // Fawry
        $fawry = TenantPaymentGateway::where('gateway', 'fawry')->first();
        if ($fawry) {
            $this->fawry_enabled = (bool) $fawry->is_enabled;
            $this->fawry_mode = $fawry->mode ?? 'sandbox';
            $this->fawry_merchant_code = (string) $fawry->getCredential('merchant_code', '');
            $this->fawry_security_key = (string) $fawry->getCredential('security_key', '');
        }

        // Kashier
        $kashier = TenantPaymentGateway::where('gateway', 'kashier')->first();
        if ($kashier) {
            $this->kashier_enabled = (bool) $kashier->is_enabled;
            $this->kashier_mode = $kashier->mode ?? 'sandbox';
            $this->kashier_merchant_id = (string) $kashier->getCredential('merchant_id', '');
            $this->kashier_api_key = (string) $kashier->getCredential('api_key', '');
        }
    }

    public function save(): void
    {
        $this->validate([
            'paymob_enabled' => ['boolean'],
            'paymob_mode' => ['required', 'in:sandbox,live'],
            'paymob_api_key' => ['nullable', 'string', 'max:500'],
            'paymob_integration_id' => ['nullable', 'string', 'max:100'],
            'paymob_iframe_id' => ['nullable', 'string', 'max:100'],
            'paymob_hmac_secret' => ['nullable', 'string', 'max:500'],
            'fake_enabled' => ['boolean'],
            'fawry_enabled' => ['boolean'],
            'fawry_mode' => ['required', 'in:sandbox,live'],
            'fawry_merchant_code' => ['nullable', 'string', 'max:255'],
            'fawry_security_key' => ['nullable', 'string', 'max:500'],
            'kashier_enabled' => ['boolean'],
            'kashier_mode' => ['required', 'in:sandbox,live'],
            'kashier_merchant_id' => ['nullable', 'string', 'max:255'],
            'kashier_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        // Save Paymob
        TenantPaymentGateway::updateOrCreate(
            ['gateway' => 'paymob'],
            [
                'is_enabled' => $this->paymob_enabled,
                'mode' => $this->paymob_mode,
                'credentials' => [
                    'api_key' => $this->paymob_api_key,
                    'integration_id' => $this->paymob_integration_id,
                    'iframe_id' => $this->paymob_iframe_id,
                    'hmac_secret' => $this->paymob_hmac_secret,
                ],
            ]
        );

        // Save Fake Gateway
        TenantPaymentGateway::updateOrCreate(
            ['gateway' => 'fake'],
            [
                'is_enabled' => $this->fake_enabled,
                'mode' => 'sandbox',
                'credentials' => [
                    'secret_key' => 'fake-secret-key',
                ],
            ]
        );

        // Save Fawry
        TenantPaymentGateway::updateOrCreate(
            ['gateway' => 'fawry'],
            [
                'is_enabled' => $this->fawry_enabled,
                'mode' => $this->fawry_mode,
                'credentials' => [
                    'merchant_code' => $this->fawry_merchant_code,
                    'security_key' => $this->fawry_security_key,
                ],
            ]
        );

        // Save Kashier
        TenantPaymentGateway::updateOrCreate(
            ['gateway' => 'kashier'],
            [
                'is_enabled' => $this->kashier_enabled,
                'mode' => $this->kashier_mode,
                'credentials' => [
                    'merchant_id' => $this->kashier_merchant_id,
                    'api_key' => $this->kashier_api_key,
                ],
            ]
        );

        Notification::make()
            ->title(__('تم حفظ إعدادات بوابات الدفع وتشفير المفاتيح بنجاح'))
            ->success()
            ->send();
    }
}
