<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\FlashSaleResource\Pages\CreateFlashSale;
use App\Filament\Store\Resources\FlashSaleResource\Pages\EditFlashSale;
use App\Filament\Store\Resources\FlashSaleResource\Pages\ListFlashSales;
use App\Models\FlashSale;
use App\Support\Money;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * @extends resource<FlashSale>
 */
class FlashSaleResource extends Resource
{
    protected static ?string $model = FlashSale::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'عروض الفلاش سيل';

    protected static ?string $modelLabel = 'فلاش سيل';

    protected static ?string $pluralModelLabel = 'عروض الفلاش سيل';

    protected static \UnitEnum|string|null $navigationGroup = 'التسويق والمبيعات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات الفلاش سيل')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('اسم الحملة / العرض')
                        ->required()
                        ->maxLength(255),

                    Toggle::make('is_active')
                        ->label('مفعل')
                        ->default(true),

                    DateTimePicker::make('starts_at')
                        ->label('تاريخ ووقت البدء')
                        ->required()
                        ->default(now()),

                    DateTimePicker::make('ends_at')
                        ->label('تاريخ ووقت الانتهاء')
                        ->required()
                        ->default(now()->addDays(2)),
                ]),

                Textarea::make('terms_and_conditions')
                    ->label('الشروط والأحكام الخاصة بالعرض')
                    ->rows(2)
                    ->nullable(),
            ]),

            Section::make('المنتجات المشمولة بالعرض والكميات المخصصة')->schema([
                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('product_variant_id')
                            ->label('المتغير / المنتج')
                            ->options(function () {
                                return ProductVariant::with(['product', 'prices'])
                                    ->get()
                                    ->mapWithKeys(function (ProductVariant $variant) {
                                        $prod = $variant->product;
                                        $prodName = $prod instanceof Product ? (string) ($prod->translateAttribute('name') ?? 'منتج') : 'منتج';
                                        $sku = $variant->sku ?? 'SKU-'.$variant->id;
                                        $firstPrice = $variant->prices->first();
                                        $stdPrice = $firstPrice instanceof Price ? $firstPrice->price->formatted() : '0 '.Money::symbol();

                                        return [$variant->id => "{$prodName} ({$sku}) - سعر أصلي: {$stdPrice}"];
                                    });
                            })
                            ->searchable()
                            ->required(),

                        TextInput::make('flash_price_minor')
                            ->label('سعر التخفيض الفوري (بالقروش - Minor Units)')
                            ->helperText('مثال: 15000 تعني 150.00 '.Money::symbol())
                            ->numeric()
                            ->required(),

                        TextInput::make('allocated_quantity')
                            ->label('الكمية المخصصة للعرض')
                            ->numeric()
                            ->default(10)
                            ->required(),

                        TextInput::make('max_per_order')
                            ->label('الحد الأقصى للقطعة في الطلب الواحد')
                            ->numeric()
                            ->default(1)
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->addActionLabel('إضافة منتج للعرض'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم العرض')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                IconColumn::make('is_active')
                    ->label('مفعل')
                    ->boolean(),

                TextColumn::make('starts_at')
                    ->label('يبدأ في')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('ينتهي في')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('عدد المنتجات')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('الحالة الحالية')
                    ->state(function (FlashSale $record): string {
                        $now = now();
                        if (! $record->is_active) {
                            return 'معطل';
                        }
                        if ($now->lt($record->starts_at)) {
                            return 'قادم قريباً';
                        }
                        if ($now->gt($record->ends_at)) {
                            return 'منتهي';
                        }

                        return 'نشط الآن ⚡';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'نشط الآن ⚡' => 'success',
                        'قادم قريباً' => 'warning',
                        'منتهي' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('items');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlashSales::route('/'),
            'create' => CreateFlashSale::route('/create'),
            'edit' => EditFlashSale::route('/{record}/edit'),
        ];
    }
}
