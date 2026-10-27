<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\Pages;

use App\Filament\Store\Resources\ProductResource;
use App\Models\WholesalePrice;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $record */
        $record = $this->getRecord();
        /** @var ProductVariant|null $variant */
        $variant = $record->variants->first();

        $data['name_ar'] = $record->translateAttribute('name') ?? '';
        $data['name_en'] = $record->translateAttribute('name', 'en') ?? $data['name_ar'];
        $data['sku'] = $variant instanceof ProductVariant ? $variant->sku : '';

        /** @var WholesalePrice|null $pricing */
        $pricing = $variant ? $variant->getRelationValue('wholesalePrice') : null;
        if ($pricing) {
            $data['wholesale_price'] = $pricing->wholesale_price_minor;
            $data['min_price'] = $pricing->min_price_minor;
            $data['max_price'] = $pricing->max_price_minor;
            $data['compare_at_price'] = $pricing->compare_at_price_minor;
            $data['status'] = $pricing->status;
            $data['pause_reason'] = $pricing->pause_reason;
            $data['is_digital'] = $pricing->is_digital;
            $data['digital_file_path'] = $pricing->digital_file_path;
        }

        /** @var Price|null $retailPrice */
        $retailPrice = $variant?->prices->first();
        if ($retailPrice) {
            $data['retail_price'] = $retailPrice->price->value;
        }

        $data['collection_ids'] = $record->collections->pluck('id')->all();

        return $data;
    }

    /**
     * @param  Product  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        $record->update([
            'status' => ($data['status'] ?? 'active') === WholesalePrice::STATUS_DRAFT ? 'draft' : 'published',
            'brand_id' => $data['brand_id'] ?? null,
            'attribute_data' => [
                'name' => new TranslatedText(collect([
                    'ar' => new Text($data['name_ar'] ?? ''),
                    'en' => new Text($data['name_en'] ?? ($data['name_ar'] ?? '')),
                ])),
            ],
        ]);

        /** @var ProductVariant|null $variant */
        $variant = $record->variants->first();
        if ($variant) {
            if (! empty($data['sku'])) {
                $variant->update(['sku' => $data['sku']]);
            }

            /** @var WholesalePrice|null $pricing */
            $pricing = $variant->getRelationValue('wholesalePrice');
            if ($pricing) {
                $pricing->update([
                    'wholesale_price_minor' => $data['wholesale_price'] ?? $pricing->wholesale_price_minor,
                    'min_price_minor' => $data['min_price'] ?? $pricing->min_price_minor,
                    'max_price_minor' => $data['max_price'] ?? $pricing->max_price_minor,
                    'compare_at_price_minor' => $data['compare_at_price'] ?? null,
                    'is_digital' => $data['is_digital'] ?? false,
                    'digital_file_path' => $data['digital_file_path'] ?? null,
                    'status' => $data['status'] ?? $pricing->status,
                    'pause_reason' => $data['pause_reason'] ?? null,
                ]);
            }

            $price = $variant->prices->first();
            if ($price && isset($data['retail_price'])) {
                $price->update([
                    'price' => $data['retail_price'],
                    'compare_price' => $data['compare_at_price'] ?? null,
                ]);
            }
        }

        if (isset($data['collection_ids'])) {
            $record->collections()->sync((array) $data['collection_ids']);
        }

        return $record;
    }
}
