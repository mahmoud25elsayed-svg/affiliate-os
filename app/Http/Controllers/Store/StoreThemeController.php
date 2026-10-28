<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Billing\Features;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ThemeSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreThemeController extends Controller
{
    public function edit(): View
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();
        $hasWhiteLabelFeature = Features::enabled('white_label', $tenant);

        $availableThemes = [
            [
                'id' => 'modern',
                'name' => 'الثيم العصري (Modern)',
                'description' => 'تصميم ديناميكي سريع ومتوافق مع كافة الأجهزة، يركز على المبيعات السريعة وتحويل الزوار.',
                'preview_badge' => 'الافتراضي',
            ],
            [
                'id' => 'boutique',
                'name' => 'بوتيك النخبة (Boutique Luxury)',
                'description' => 'تصميم هادئ وفاخر بلمسات ذهبية وألوان دافئة، مثالي للملابس، المجوهرات، العطور والمنتجات الراقية.',
                'preview_badge' => 'جديد',
            ],
        ];

        return view('store.theme.customize', [
            'tenant' => $tenant,
            'theme' => $theme,
            'general' => $general,
            'hasWhiteLabelFeature' => $hasWhiteLabelFeature,
            'availableThemes' => $availableThemes,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        $validated = $request->validate([
            'theme_id' => ['required', 'string', 'in:modern,boutique'],
            'primary_color' => ['required', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'font_family' => ['required', 'string', 'in:Cairo,Almarai,Tajawal,Alexandria,IBM Plex Sans Arabic'],
            'hero_headline' => ['required', 'string', 'max:255'],
            'hero_subheadline' => ['nullable', 'string', 'max:500'],
            'hero_button_text' => ['required', 'string', 'max:50'],
            'hero_button_url' => ['required', 'string', 'max:255'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'show_hero_banner' => ['nullable', 'boolean'],
            'show_features_badges' => ['nullable', 'boolean'],
            'show_featured_categories' => ['nullable', 'boolean'],
            'show_featured_products' => ['nullable', 'boolean'],
            'show_offers_banner' => ['nullable', 'boolean'],
            'show_latest_products' => ['nullable', 'boolean'],
            'white_label_enabled' => ['nullable', 'boolean'],
            'about_us' => ['nullable', 'string'],
            'terms_and_conditions' => ['nullable', 'string'],
            'privacy_policy' => ['nullable', 'string'],
            'shipping_and_returns' => ['nullable', 'string'],
            'custom_css' => ['nullable', 'string', 'max:10000'],
        ]);

        $theme = ThemeSettings::load();

        $theme->theme_id = $validated['theme_id'];
        $theme->primary_color = $validated['primary_color'];
        if (! empty($validated['secondary_color'])) {
            $theme->secondary_color = $validated['secondary_color'];
        }
        if (! empty($validated['accent_color'])) {
            $theme->accent_color = $validated['accent_color'];
        }
        $theme->font_family = $validated['font_family'];

        $theme->hero_headline = $validated['hero_headline'];
        $theme->hero_subheadline = $validated['hero_subheadline'] ?? '';
        $theme->hero_button_text = $validated['hero_button_text'];
        $theme->hero_button_url = $validated['hero_button_url'];
        $theme->hero_image_url = $validated['hero_image_url'] ?? null;

        $theme->show_hero_banner = (bool) $request->boolean('show_hero_banner');
        $theme->show_features_badges = (bool) $request->boolean('show_features_badges');
        $theme->show_featured_categories = (bool) $request->boolean('show_featured_categories');
        $theme->show_featured_products = (bool) $request->boolean('show_featured_products');
        $theme->show_offers_banner = (bool) $request->boolean('show_offers_banner');
        $theme->show_latest_products = (bool) $request->boolean('show_latest_products');

        if (Features::enabled('white_label', $tenant)) {
            $theme->white_label_enabled = (bool) $request->boolean('white_label_enabled');
        }

        if (isset($validated['about_us'])) {
            $theme->about_us = $validated['about_us'];
        }
        if (isset($validated['terms_and_conditions'])) {
            $theme->terms_and_conditions = $validated['terms_and_conditions'];
        }
        if (isset($validated['privacy_policy'])) {
            $theme->privacy_policy = $validated['privacy_policy'];
        }
        if (isset($validated['shipping_and_returns'])) {
            $theme->shipping_and_returns = $validated['shipping_and_returns'];
        }
        $theme->custom_css = $validated['custom_css'] ?? null;

        // Custom sections ordering if submitted
        if ($request->has('sections_order') && is_array($request->input('sections_order'))) {
            /** @var array<string> $rawOrder */
            $rawOrder = $request->input('sections_order');
            $theme->sections_order = array_values(array_filter($rawOrder, 'is_string'));
        }

        $theme->save();

        return redirect()
            ->route('store.theme.edit')
            ->with('success', 'تم حفظ تخصيصات المظهر والثيم بنجاح.');
    }
}
