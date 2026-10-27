<?php

declare(strict_types=1);

namespace App\Jobs\Catalog;

use App\Models\Tenant;
use App\Models\WholesalePrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant
    ) {}

    public function handle(): string
    {
        return (string) $this->tenant->run(function () {
            $output = fopen('php://temp', 'r+');
            if (! $output) {
                return '';
            }

            fputcsv($output, [
                'name',
                'sku',
                'retail_price',
                'wholesale_price',
                'min_price',
                'max_price',
                'compare_at_price',
                'status',
                'pause_reason',
                'is_digital',
            ]);

            $products = Product::with(['variants.wholesalePrice', 'variants.prices'])->get();

            foreach ($products as $product) {
                /** @var Product $product */
                $name = $product->translateAttribute('name') ?? '';

                foreach ($product->variants as $variant) {
                    /** @var ProductVariant $variant */
                    /** @var WholesalePrice|null $pricing */
                    $pricing = $variant->getRelationValue('wholesalePrice');
                    /** @var Price|null $retailPrice */
                    $retailPrice = $variant->prices->first();

                    fputcsv($output, [
                        $name,
                        $variant->sku,
                        $retailPrice ? (string) $retailPrice->price->decimal() : '0.00',
                        $pricing ? $pricing->wholesalePriceMoney()->toDecimalString() : '0.00',
                        $pricing ? $pricing->minPriceMoney()->toDecimalString() : '0.00',
                        $pricing ? $pricing->maxPriceMoney()->toDecimalString() : '0.00',
                        ($pricing && $pricing->compareAtPriceMoney()) ? $pricing->compareAtPriceMoney()->toDecimalString() : '',
                        $pricing ? $pricing->status : $product->status,
                        $pricing ? $pricing->pause_reason : '',
                        ($pricing && $pricing->is_digital) ? '1' : '0',
                    ]);
                }
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            return $csv ?: '';
        });
    }
}
