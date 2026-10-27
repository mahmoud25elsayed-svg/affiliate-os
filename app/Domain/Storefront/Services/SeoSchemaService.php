<?php

declare(strict_types=1);

namespace App\Domain\Storefront\Services;

use App\Support\Settings\GeneralSettings;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SeoSchemaService
{
    /**
     * Generate schema.org Organization / OnlineStore JSON-LD.
     */
    public function organizationSchema(): string
    {
        $settings = GeneralSettings::load();
        $storeName = $settings->platform_name;
        $url = url('/');
        $logo = $settings->logo_url ?: $url.'/logo.png';

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'OnlineStore',
            'name' => $storeName,
            'url' => $url,
            'logo' => $logo,
            'telephone' => $settings->support_phone,
            'email' => $settings->support_email,
            'priceRange' => 'EGP',
        ];

        return (string) json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate schema.org Product & Offer JSON-LD.
     */
    public function productSchema(Product $product): string
    {
        $name = (string) ($product->translateAttribute('name') ?? 'منتج');
        $description = (string) ($product->translateAttribute('description') ?? $name);

        /** @var ProductVariant|null $variant */
        $variant = $product->variants->first();
        /** @var Price|null $price */
        $price = $variant?->prices->first();
        $priceValue = $price ? (float) $price->price->decimal() : 0.0;
        $sku = $variant instanceof ProductVariant ? $variant->sku : 'SKU-'.$product->id;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => $description,
            'sku' => $sku,
            'offers' => [
                '@type' => 'Offer',
                'url' => url('/products/'.$product->id),
                'priceCurrency' => 'EGP',
                'price' => number_format($priceValue, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        $thumbnail = $product->thumbnail;
        if ($thumbnail instanceof Media) {
            $schema['image'] = [$thumbnail->getUrl()];
        }

        return (string) json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate schema.org BreadcrumbList JSON-LD.
     *
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public function breadcrumbSchema(array $items): string
    {
        $itemList = [];
        foreach ($items as $index => $item) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList,
        ];

        return (string) json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
