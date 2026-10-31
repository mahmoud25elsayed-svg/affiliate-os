<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ThemeSettings;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        return view('storefront.page', [
            'title' => __('عن المتجر'),
            'content' => __($theme->about_us),
            'theme' => $theme,
            'general' => $general,
        ]);
    }

    public function terms(): View
    {
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        return view('storefront.page', [
            'title' => __('الشروط والأحكام'),
            'content' => __($theme->terms_and_conditions),
            'theme' => $theme,
            'general' => $general,
        ]);
    }

    public function privacy(): View
    {
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        return view('storefront.page', [
            'title' => __('سياسة الخصوصية'),
            'content' => __($theme->privacy_policy),
            'theme' => $theme,
            'general' => $general,
        ]);
    }

    public function shipping(): View
    {
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        return view('storefront.page', [
            'title' => __('الشحن والاسترجاع'),
            'content' => __($theme->shipping_and_returns),
            'theme' => $theme,
            'general' => $general,
        ]);
    }

    public function show(string $slug): View
    {
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        /** @var CustomPage $page */
        $page = CustomPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('storefront.page', [
            'title' => $page->title,
            'content' => $page->content,
            'metaTitle' => $page->meta_title ?? $page->title,
            'metaDescription' => $page->meta_description,
            'theme' => $theme,
            'general' => $general,
        ]);
    }
}
