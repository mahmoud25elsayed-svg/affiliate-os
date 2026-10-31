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

    public static function getNavigationLabel(): string
    {

        return __('عروض الفلاش سيل');

    }

    public static function getModelLabel(): string
    {
        return __('فلاش سيل');
    }

    public static function getPluralModelLabel(): string
    {
        return __('عروض الفلاش سيل');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('التسويق والمبيعات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('معلومات الفلاش سيل'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('اسم الحملة / العرض'))
                        ->required()
                        ->maxLength(255),

                    Toggle::make('is_active')
                        ->label(__('مفعل'))
                        ->default(true),

                    DateTimePicker::make('starts_at')
                        ->label(__('تاريخ ووقت البدء'))
                        ->required()
                        ->default(now()),

                    DateTimePicker::make('ends_at')
                        ->label(__('تاريخ ووقت الانتهاء'))
                        ->required()
                        ->default(now()->addDays(2)),
                ]),

                Textarea::make('terms_and_conditions')
                    ->label(__('الشروط والأحكام الخاصة بالعرض'))
                    ->rows(2)
                    ->nullable(),
            ]),

            Section::make(__('المنتجات المشمولة بالعرض والكميات المخصصة'))->schema([
                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('product_variant_id')
                            ->label(__('المتغير / المنتج'))
                            ->options(function () {
                                return ProductVariant::with(['product', 'prices'])
                                    ->get()
                                    ->mapWithKeys(function (ProductVariant $variant) {
                                        $prod = $variant->product;
                                        $prodName = $prod instanceof Product ? (string) ($prod->translateAttribute('name') ?? __('منتج')) : __('منتج');
                                        $sku = $variant->sku ?? 'SKU-'.$variant->id;
                                        $firstPrice = $variant->prices->first();
                                        $stdPrice = $firstPrice instanceof Price ? $firstPrice->price->formatted() : '0 '.Money::symbol();

                                        return [$variant->id => __(':prod_name (:sku) - سعر أصلي: :std_price', ['prod_name' => $prodName, 'sku' => $sku, 'std_price' => $stdPrice])];
                                    });
                            })
                            ->searchable()
                            ->required(),

                        TextInput::make('flash_price_minor')
                            ->label(__('سعر التخفيض الفوري (بالقروش - Minor Units)'))
                            ->helperText(__('مثال: 15000 تعني 150.00 ').Money::symbol())
                            ->numeric()
                            ->required(),

                        TextInput::make('allocated_quantity')
                            ->label(__('الكمية المخصصة للعرض'))
                            ->numeric()
                            ->default(10)
                            ->required(),

                        TextInput::make('max_per_order')
                            ->label(__('الحد الأقصى للقطعة في الطلب الواحد'))
                            ->numeric()
                            ->default(1)
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->addActionLabel(__('إضافة منتج للعرض')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('اسم العرض'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                IconColumn::make('is_active')
                    ->label(__('مفعل'))
                    ->boolean(),

                TextColumn::make('starts_at')
                    ->label(__('يبدأ في'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('ينتهي في'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label(__('عدد المنتجات'))
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label(__('الحالة الحالية'))
                    ->state(function (FlashSale $record): string {
                        $now = now();
                        if (! $record->is_active) {
                            return __('معطل');
                        }
                        if ($now->lt($record->starts_at)) {
                            return __('قادم قريباً');
                        }
                        if ($now->gt($record->ends_at)) {
                            return __('منتهي');
                        }

                        return __('نشط الآن ⚡');
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
