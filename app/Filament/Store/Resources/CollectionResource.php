<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\CollectionResource\Pages\ListCollections;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;

/**
 * @extends resource<Collection>
 */
class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'الفئات والتصنيفات';

    protected static ?string $modelLabel = 'فئة';

    protected static ?string $pluralModelLabel = 'الفئات والتصنيفات';

    protected static \UnitEnum|string|null $navigationGroup = 'إدارة الكتالوج';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الفئة')->schema([
                TextInput::make('name_ar')
                    ->label('اسم الفئة (بالعربية)')
                    ->required(),

                TextInput::make('name_en')
                    ->label('اسم الفئة (بالإنجليزية)'),

                Select::make('collection_group_id')
                    ->label('مجموعة الفئات')
                    ->options(fn () => CollectionGroup::pluck('name', 'id'))
                    ->default(fn () => CollectionGroup::first()?->id)
                    ->required(),

                Select::make('parent_id')
                    ->label('الفئة الأب (إن وجدت)')
                    ->options(fn () => Collection::all()->mapWithKeys(function ($c) {
                        /** @var Collection $c */
                        return [$c->id => (string) ($c->translateAttribute('name') ?? 'فئة #'.$c->id)];
                    }))
                    ->nullable()
                    ->searchable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الفئة')
                    ->getStateUsing(fn (Collection $record) => $record->translateAttribute('name') ?? 'فئة #'.$record->id)
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('parent.name')
                    ->label('الفئة الأب')
                    ->getStateUsing(fn (Collection $record) => $record->parent instanceof Collection ? $record->parent->translateAttribute('name') : '-')
                    ->badge(),

                TextColumn::make('products_count')
                    ->label('عدد المنتجات')
                    ->counts('products')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollections::route('/'),
        ];
    }
}
