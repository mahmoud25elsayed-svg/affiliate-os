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

    protected static ?string $navigationLabel = 'العلامات التجارية';

    protected static ?string $modelLabel = 'علامة تجارية';

    protected static ?string $pluralModelLabel = 'العلامات التجارية';

    protected static \UnitEnum|string|null $navigationGroup = 'إدارة الكتالوج';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('العلامة التجارية')->schema([
                TextInput::make('name')
                    ->label('اسم العلامة التجارية')
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
                    ->label('اسم العلامة التجارية')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

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
            'index' => ListBrands::route('/'),
        ];
    }
}
