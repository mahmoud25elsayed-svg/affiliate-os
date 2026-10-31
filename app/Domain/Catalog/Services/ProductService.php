<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

use App\Domain\Inventory\Actions\InboundStockAction;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Billing\Limits;
use DomainException;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

class ProductService
{
    /**
     * @param  array{
     *     name?: string,
     *     name_ar?: string,
     *     name_en?: string,
     *     sku?: string,
     *     status?: string,
     *     pause_reason?: string|null,
     *     product_type_id?: int,
     *     brand_id?: int|null,
     *     retail_price?: int,
     *     wholesale_price?: int,
     *     min_price?: int,
     *     max_price?: int,
     *     compare_at_price?: int|null,
     *     is_digital?: bool,
     *     digital_file_path?: string|null,
     *     collection_ids?: array<int>,
     *     opening_stock?: int|string|null,
     * }  $data
     */
    public function createProduct(array $data, ?Tenant $tenant = null): Product
    {
        // 1. Enforce product limit at the service level!
        Limits::ensure('products', $tenant);

        // 2. Validate price range: max >= min >= wholesale
        $wholesale = $data['wholesale_price'] ?? 0;
        $min = $data['min_price'] ?? $wholesale;
        $max = $data['max_price'] ?? $min;

        if ($max < $min) {
            throw new DomainException(__('الحد الأقصى لسعر البيع يجب أن يكون أكبر من أو يساوي الحد الأدنى.'));
        }

        if ($min < $wholesale) {
            throw new DomainException(__('الحد الأدنى لسعر البيع يجب أن يكون أكبر من أو يساوي سعر الجملة.'));
        }

        $status = $data['status'] ?? WholesalePrice::STATUS_ACTIVE;
        $pauseReason = $data['pause_reason'] ?? null;

        if ($status === WholesalePrice::STATUS_PAUSED && empty(trim((string) $pauseReason))) {
            throw new DomainException(__('يجب تحديد سبب إيقاف المنتج عند تعليقه.'));
        }

        $productType = isset($data['product_type_id'])
            ? ProductType::findOrFail($data['product_type_id'])
            : (ProductType::first() ?? ProductType::create(['name' => 'Standard']));

        $nameAr = $data['name_ar'] ?? $data['name'] ?? 'Product '.uniqid();
        $nameEn = $data['name_en'] ?? $nameAr;

        /** @var Product $product */
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => $status === WholesalePrice::STATUS_DRAFT ? 'draft' : 'published',
            'brand_id' => $data['brand_id'] ?? null,
            'attribute_data' => [
                'name' => new TranslatedText(collect([
                    'ar' => new Text($nameAr),
                    'en' => new Text($nameEn),
                ])),
            ],
        ]);

        $taxClass = TaxClass::first() ?? TaxClass::create(['name' => 'Default']);
        $sku = $data['sku'] ?? ('SKU-'.strtoupper(Str::random(8)));

        /** @var ProductVariant $variant */
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'tax_class_id' => $taxClass->id,
        ]);

        // Create retail price
        $currency = Currency::getDefault() ?? Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
        );

        $retailPrice = $data['retail_price'] ?? $min;

        Price::create([
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
            'currency_id' => $currency->id,
            'price' => $retailPrice,
            'compare_price' => $data['compare_at_price'] ?? null,
        ]);

        // Create Wholesale / Affiliate pricing extension
        WholesalePrice::create([
            'product_variant_id' => $variant->id,
            'wholesale_price_minor' => $wholesale,
            'min_price_minor' => $min,
            'max_price_minor' => $max,
            'compare_at_price_minor' => $data['compare_at_price'] ?? null,
            'is_digital' => $data['is_digital'] ?? false,
            'digital_file_path' => $data['digital_file_path'] ?? null,
            'status' => $status,
            'pause_reason' => $pauseReason,
        ]);

        // Attach collections if provided
        if (! empty($data['collection_ids'])) {
            $product->collections()->sync($data['collection_ids']);
        }

        // Opening stock goes through the warehouse ledger so orders can reserve it.
        $openingStock = (int) ($data['opening_stock'] ?? 0);
        if ($openingStock > 0) {
            app(InboundStockAction::class)->execute(
                variantId: (int) $variant->id,
                quantity: $openingStock,
                warehouseId: Warehouse::resolveDefault()->id,
                referenceType: 'opening_stock',
                referenceId: (string) $product->id,
                actorId: auth()->id() !== null ? (string) auth()->id() : null,
            );
        }

        return $product;
    }

    public function updateStatus(Product $product, string $status, ?string $reason = null): void
    {
        if ($status === WholesalePrice::STATUS_PAUSED && empty(trim((string) $reason))) {
            throw new DomainException(__('يجب تحديد سبب إيقاف المنتج عند تعليقه.'));
        }

        $lunarStatus = match ($status) {
            WholesalePrice::STATUS_DRAFT => 'draft',
            WholesalePrice::STATUS_ARCHIVED => 'archived',
            default => 'published',
        };

        $product->update(['status' => $lunarStatus]);

        foreach ($product->variants as $variant) {
            /** @var ProductVariant $variant */
            $pricing = WholesalePrice::where('product_variant_id', $variant->id)->first();
            if ($pricing) {
                $pricing->update([
                    'status' => $status,
                    'pause_reason' => $reason,
                ]);
            }
        }
    }
}
