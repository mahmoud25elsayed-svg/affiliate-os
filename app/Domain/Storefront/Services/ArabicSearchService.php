<?php

declare(strict_types=1);

namespace App\Domain\Storefront\Services;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Product;

class ArabicSearchService
{
    /**
     * Normalize Arabic string by stripping diacritics, unifying alefs, yaas, and taa marbuta.
     */
    public static function normalize(string $text): string
    {
        // 1. Remove Tashkeel / Harakat (diacritics)
        $tashkeel = ['ً', 'ٌ', 'ٍ', 'َ', 'ُ', 'ِ', 'ّ', 'ْ', 'ـ'];
        $text = str_replace($tashkeel, '', $text);

        // 2. Unify Alefs
        $text = (string) preg_replace('/[أإآٱ]/u', 'ا', $text);

        // 3. Unify Taa Marbuta to Haa
        $text = (string) preg_replace('/ة/u', 'ه', $text);

        // 4. Unify Alef Maksura to Yaa
        $text = (string) preg_replace('/ى/u', 'ي', $text);

        // 5. Clean whitespace
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Generate common Arabic letter variations (alefs, taa marbuta, yaa).
     *
     * @return list<string>
     */
    public static function generateVariations(string $keyword): array
    {
        $normalized = self::normalize($keyword);
        $variations = [$keyword, $normalized];

        // Alefs variations
        if (str_contains($normalized, 'ا')) {
            $variations[] = (string) preg_replace('/ا/u', 'أ', $normalized);
            $variations[] = (string) preg_replace('/ا/u', 'إ', $normalized);
            $variations[] = (string) preg_replace('/ا/u', 'آ', $normalized);
        }

        // Taa marbuta and alef maksura variations
        $current = $variations;
        foreach ($current as $term) {
            if (str_ends_with($term, 'ه')) {
                $variations[] = mb_substr($term, 0, -1).'ة';
            } elseif (str_ends_with($term, 'ة')) {
                $variations[] = mb_substr($term, 0, -1).'ه';
            }

            if (str_ends_with($term, 'ي')) {
                $variations[] = mb_substr($term, 0, -1).'ى';
            } elseif (str_ends_with($term, 'ى')) {
                $variations[] = mb_substr($term, 0, -1).'ي';
            }
        }

        return array_values(array_unique(array_filter($variations)));
    }

    /**
     * Build search query for products supporting normalized Arabic terms and Latin SKU/keywords.
     *
     * @return Builder<Product>
     */
    public function search(string $keyword): Builder
    {
        $variations = self::generateVariations($keyword);
        $raw = trim($keyword);

        return Product::query()
            ->where('status', 'published')
            ->where(function (Builder $q) use ($variations, $raw) {
                foreach ($variations as $term) {
                    $q->orWhere('attribute_data', 'like', "%{$term}%");

                    $escaped = trim((string) json_encode($term), '"');
                    if ($escaped !== '' && $escaped !== $term) {
                        $q->orWhere('attribute_data', 'like', "%{$escaped}%");
                    }
                }
                $q->orWhereHas('variants', function (Builder $vq) use ($raw) {
                    $vq->where('sku', 'like', "%{$raw}%");
                });
            });
    }
}
