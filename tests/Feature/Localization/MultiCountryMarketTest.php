<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use App\Support\Location\Country;
use App\Support\Location\Governorate;
use App\Support\Location\Market;
use App\Support\Money;
use App\Support\Phone\PhoneNumber;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ShippingSettings;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Lunar\Models\Currency;
use Tests\TestCase;

class MultiCountryMarketTest extends TestCase
{
    public function test_every_configured_country_has_a_currency_and_a_valid_example_number(): void
    {
        foreach (Country::all() as $code => $country) {
            $this->assertIsArray(config("currencies.{$country->currency}"), "{$code} currency missing");

            $phone = PhoneNumber::tryParse($country->example, $code);
            $this->assertNotNull($phone, "{$code} example {$country->example} does not validate");
            $this->assertSame($code === 'CA' ? 'CA' : $code, $phone->country);
            $this->assertStringStartsWith('+'.$country->dial, $phone->e164);
        }
    }

    public function test_phone_numbers_parse_locally_and_internationally(): void
    {
        $sa = PhoneNumber::parse('0512345678', 'SA');
        $this->assertSame('+966512345678', $sa->e164);
        $this->assertSame('0512345678', $sa->national);

        // International input wins over the local country, Arabic-Indic digits are accepted.
        $kw = PhoneNumber::parse('+965 5001 2345', 'EG');
        $this->assertSame(['+96550012345', 'KW'], [$kw->e164, $kw->country]);
        $this->assertSame('+201012345678', PhoneNumber::parse('٠١٠١٢٣٤٥٦٧٨', 'EG')->e164);
        $this->assertSame('+971501234567', PhoneNumber::parse('00971501234567', 'EG')->e164);

        // Landlines and wrong lengths are rejected (COD needs a reachable mobile).
        $this->assertNull(PhoneNumber::tryParse('0223456789', 'EG'));
        $this->assertNull(PhoneNumber::tryParse('051234567', 'SA'));

        // Restricting to shipping countries.
        $this->assertNull(PhoneNumber::tryParse('+966512345678', 'EG', ['EG']));
    }

    public function test_egyptian_hash_is_unchanged_so_existing_records_still_match(): void
    {
        $key = 'k';
        $this->assertSame(hash_hmac('sha256', '+201012345678', $key), PhoneNumber::parse('01012345678', 'EG')->hash($key));
    }

    public function test_money_formats_with_each_currency_symbol_and_decimals(): void
    {
        $this->assertSame('1,250.50 ج.م', Money::fromMinor(125050, 'EGP')->format('ar'));
        $this->assertSame('12.500 د.ك', Money::fromMinor(1250, 'KWD')->format('ar'));
        $this->assertSame('250,000 د.ع', Money::fromMinor(25000000, 'IQD')->format('ar'));
        $this->assertSame('$ 19.99', Money::fromMinor(1999, 'USD')->format('en'));
        $this->assertSame('SAR 100.00', Money::fromMinor(10000, 'SAR')->format('en'));
        $this->assertSame('-5.00 ر.س', Money::fromMinor(-500, 'SAR')->format('ar'));
    }

    public function test_regions_are_seeded_per_country_on_first_use(): void
    {
        $this->seed(EgyptGovernoratesSeeder::class);

        $saudi = Governorate::forCountries(['SA']);
        $this->assertCount(13, $saudi);
        $this->assertTrue($saudi->every(fn (Governorate $g): bool => $g->country_code === 'SA'));
        $this->assertCount(13, Governorate::forCountries(['SA']), 'second call must not duplicate rows');

        $egypt = Governorate::forCountries(['EG']);
        $this->assertGreaterThan(20, $egypt->count());
        $this->assertTrue($egypt->every(fn (Governorate $g): bool => $g->country_code === 'EG'));

        $this->assertCount(13 + 7, Governorate::forCountries(['SA', 'AE']));
    }

    public function test_a_store_provisioned_in_saudi_arabia_sells_in_riyal(): void
    {
        $this->seed(PlanSeeder::class);

        $tenant = (new ProvisionTenant('riyadh-shop', 'متجر الرياض', 'riyadh-shop.localhost', [], 'SA'))->handle();

        $tenant->run(function (): void {
            $settings = GeneralSettings::load();
            $this->assertSame(['SA', 'SAR', 'ar'], [$settings->country_code, $settings->default_currency, $settings->default_locale]);
            $this->assertSame('SAR', Market::currency());
            $this->assertSame('SAR', Currency::query()->where('default', true)->value('code'));
            $this->assertSame('ر.س', Money::symbol(locale: 'ar'));
            $this->assertSame('+966512345678', PhoneNumber::parse('0512345678')->e164, 'local numbers use the store country');
            $this->assertSame([2500, 1000], [ShippingSettings::load()->default_rate_minor, ShippingSettings::load()->cod_fee_minor]);
        });

        $this->assertInstanceOf(Tenant::class, $tenant);
    }

    public function test_a_store_in_an_english_speaking_market_defaults_to_english(): void
    {
        $this->seed(PlanSeeder::class);

        (new ProvisionTenant('london-shop', 'London Shop', 'london-shop.localhost', [], 'GB'))->handle()
            ->run(fn () => $this->assertSame(['GBP', 'en'], [GeneralSettings::load()->default_currency, GeneralSettings::load()->default_locale]));
    }
}
