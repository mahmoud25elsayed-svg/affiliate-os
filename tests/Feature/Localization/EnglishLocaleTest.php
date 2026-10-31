<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Http\Middleware\SetLocale;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnglishLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_default_language_is_arabic_and_rtl(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_lang_query_switches_to_english_ltr_and_is_remembered_in_the_session(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Launch your own store')
            ->assertDontSee('أنشئ متجرك الآن');

        // No query string: the earlier choice sticks.
        $this->get('/pricing')->assertOk()->assertSee('<html lang="en" dir="ltr">', false);

        // Switching back works too.
        $this->get('/?lang=ar')->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
        $this->get('/pricing')->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_unsupported_language_falls_back_to_the_default(): void
    {
        $this->get('/?lang=xx')->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
        $this->get('/?lang="><script>alert(1)</script>')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame(['ar', 'en'], SetLocale::SUPPORTED);
    }

    public function test_english_pages_do_not_leak_arabic_marketing_copy(): void
    {
        foreach (['/?lang=en', '/pricing', '/features', '/faq'] as $url) {
            $body = $this->get($url)->assertOk()->getContent();
            // Strip data that is legitimately Arabic (the language switch label) before checking.
            $body = str_replace('العربية', '', (string) $body);
            preg_match('/<(h1|h2|h3|p|a|button)[^>]*>\s*[^<]*[\x{0600}-\x{06FF}][^<]*/u', $body, $leak);
            $this->assertSame([], $leak, "{$url} still renders Arabic text in English mode");
        }
    }

    public function test_structured_data_is_valid_json_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $lang) {
            foreach (['/', '/pricing', '/faq'] as $url) {
                $body = $this->get("{$url}?lang={$lang}")->assertOk()->getContent();
                preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', (string) $body, $blocks);

                foreach ($blocks[1] as $json) {
                    $decoded = json_decode($json, true);
                    $this->assertNotNull($decoded, "{$url}?lang={$lang} has invalid JSON-LD: ".json_last_error_msg());
                }
            }
        }
    }

    public function test_open_graph_locale_and_schema_language_follow_the_interface_language(): void
    {
        $this->get('/?lang=en')->assertSee('content="en_US"', false)->assertSee('"inLanguage": "en"', false);
        $this->get('/?lang=ar')->assertSee('content="ar_EG"', false)->assertSee('"inLanguage": "ar"', false);
    }

    public function test_every_arabic_translation_key_has_a_matching_english_entry(): void
    {
        $en = json_decode((string) file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($en);

        $missing = [];
        foreach ([app_path(), resource_path('views')] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! preg_match('/\.php$/', $file->getFilename())) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*[,)]/u", $source, $single);
                preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*[,)]/u', $source, $double);

                foreach (array_merge($single[1], $double[1]) as $raw) {
                    $key = stripcslashes($raw);
                    if (preg_match('/\p{Arabic}/u', $key) && ! array_key_exists($key, $en)) {
                        $missing[$key] = $file->getFilename();
                    }
                }
            }
        }

        $this->assertSame([], array_slice($missing, 0, 10, true), count($missing).' Arabic keys have no English translation');
    }

    public function test_english_values_keep_the_same_placeholders_as_their_arabic_keys(): void
    {
        $en = json_decode((string) file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($en);

        $broken = [];
        foreach ($en as $key => $value) {
            $this->assertNotSame('', trim((string) $value), "empty English value for {$key}");
            preg_match_all('/:[a-z_]+/i', (string) $key, $keyTokens);
            preg_match_all('/:[a-z_]+/i', (string) $value, $valueTokens);
            $expected = array_unique($keyTokens[0]);
            $actual = array_unique($valueTokens[0]);
            sort($expected);
            sort($actual);
            if ($expected !== $actual) {
                $broken[$key] = [$expected, $actual];
            }
        }

        $this->assertSame([], array_slice($broken, 0, 5, true), count($broken).' English values changed placeholders');
    }
}
