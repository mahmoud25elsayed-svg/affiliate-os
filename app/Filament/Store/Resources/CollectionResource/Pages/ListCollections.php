<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\CollectionResource\Pages;

use App\Filament\Store\Resources\CollectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;

class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('إضافة فئة جديدة'))
                ->using(function (array $data): Collection {
                    /** @var CollectionGroup $group */
                    $group = isset($data['collection_group_id'])
                        ? CollectionGroup::findOrFail($data['collection_group_id'])
                        : (CollectionGroup::first() ?? CollectionGroup::create(['name' => 'Default', 'handle' => 'default']));

                    $nameAr = $data['name_ar'] ?? __('فئة جديدة');
                    $nameEn = $data['name_en'] ?? $nameAr;

                    /** @var Collection $collection */
                    $collection = Collection::create([
                        'collection_group_id' => $group->id,
                        'parent_id' => $data['parent_id'] ?? null,
                        'attribute_data' => [
                            'name' => new TranslatedText(collect([
                                'ar' => new Text($nameAr),
                                'en' => new Text($nameEn),
                            ])),
                        ],
                    ]);

                    return $collection;
                }),
        ];
    }
}
