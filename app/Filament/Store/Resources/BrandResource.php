<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\BrandResource\Pages\ListBrands;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Models\Brand;

/**
 * @extends resource<Brand>
 */
class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationLabel(): string
    {

        return __('العلامات التجارية');

    }

    public static function getModelLabel(): string
    {
        return __('علامة تجارية');
    }

    public static function getPluralModelLabel(): string
    {
        return __('العلامات التجارية');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('إدارة الكتالوج');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('العلامة التجارية'))->schema([
                TextInput::make('name')
                    ->label(__('اسم العلامة التجارية'))
                    ->required()
                    ->maxLength(255),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('اسم العلامة التجارية'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('products_count')
                    ->label(__('عدد المنتجات'))
                    ->counts('products')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الإنشاء'))
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
        ];
    }
}
