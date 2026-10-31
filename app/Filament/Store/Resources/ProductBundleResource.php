<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\ProductBundleResource\Pages\CreateProductBundle;
use App\Filament\Store\Resources\ProductBundleResource\Pages\EditProductBundle;
use App\Filament\Store\Resources\ProductBundleResource\Pages\ListProductBundles;
use App\Models\ProductBundle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Models\Product;

/**
 * @extends resource<ProductBundle>
 */
class ProductBundleResource extends Resource
{
    protected static ?string $model = ProductBundle::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    public static function getNavigationLabel(): string
    {

        return __('عروض الباقات (Buy X Get Y)');

    }

    public static function getModelLabel(): string
    {
        return __('باقة / عرض مجمع');
    }

    public static function getPluralModelLabel(): string
    {
        return __('عروض الباقات والـ Bundles');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('التسويق والمبيعات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('معلومات الباقة والعرض المجمع'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('اسم العرض'))
                        ->placeholder(__('مثال: اشترِ 2 واحصل على 1 مجاناً'))
                        ->required()
                        ->maxLength(255),

                    Select::make('bundle_type')
                        ->label(__('نوع الباقة'))
                        ->options([
                            'buy_x_get_y' => __('اشترِ X واحصل على Y (Buy X Get Y)'),
                            'combo_discount' => __('تخفيض كومبو مجمع (Combo Discount)'),
                        ])
                        ->required()
                        ->default('buy_x_get_y'),

                    Select::make('primary_product_id')
                        ->label(__('المنتج الأساسي المطلوب شراؤه'))
                        ->options(function () {
                            return Product::all()->mapWithKeys(function (Product $prod) {
                                return [$prod->id => (string) ($prod->translateAttribute('name') ?? __('منتج #:id', ['id' => $prod->id]))];
                            });
                        })
                        ->searchable()
                        ->required(),

                    TextInput::make('required_quantity')
                        ->label(__('الكمية المطلوبة من المنتج الأساسي (X)'))
                        ->numeric()
                        ->default(2)
                        ->required(),

                    Select::make('reward_product_id')
                        ->label(__('منتج الهدية أو العرض (Y) - اتركه فارغاً إذا كان نفس المنتج'))
                        ->options(function () {
                            return Product::all()->mapWithKeys(function (Product $prod) {
                                return [$prod->id => (string) ($prod->translateAttribute('name') ?? __('منتج #:id', ['id' => $prod->id]))];
                            });
                        })
                        ->searchable()
                        ->nullable(),

                    TextInput::make('reward_quantity')
                        ->label(__('كمية الهدية / العرض (Y)'))
                        ->numeric()
                        ->default(1)
                        ->required(),

                    TextInput::make('reward_discount_percent')
                        ->label(__('نسبة خصم منتج الهدية (%)'))
                        ->helperText(__('100 تعني مجاناً تماماً، 50 تعني خصم نصف السعر على القطعة الإضافية'))
                        ->numeric()
                        ->default(100)
                        ->required(),

                    Toggle::make('is_active')
                        ->label(__('مفعل'))
                        ->default(true),

                    DateTimePicker::make('starts_at')
                        ->label(__('تاريخ البدء (اختياري)'))
                        ->nullable(),

                    DateTimePicker::make('ends_at')
                        ->label(__('تاريخ الانتهاء (اختياري)'))
                        ->nullable(),
                ]),
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
                    ->weight('bold'),

                TextColumn::make('bundle_type')
                    ->label(__('نوع العرض'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'buy_x_get_y' => 'Buy X Get Y',
                        'combo_discount' => 'Combo Discount',
                        default => $state,
                    })
                    ->color('info'),

                TextColumn::make('rule')
                    ->label(__('قاعدة العرض'))
                    ->state(function (ProductBundle $record): string {
                        $buyQty = $record->required_quantity;
                        $buyProd = $record->primary_product_id ? Product::find($record->primary_product_id) : null;
                        $primary = $buyProd instanceof Product ? (string) ($buyProd->translateAttribute('name') ?? __('منتج #:primary_product_id', ['primary_product_id' => $record->primary_product_id])) : __('منتج #:primary_product_id', ['primary_product_id' => $record->primary_product_id]);

                        $getQty = $record->reward_quantity;
                        $getProd = $record->reward_product_id ? Product::find($record->reward_product_id) : null;
                        $reward = $getProd instanceof Product ? (string) ($getProd->translateAttribute('name') ?? __('منتج #:reward_product_id', ['reward_product_id' => $record->reward_product_id])) : ($record->reward_product_id ? __('منتج #:reward_product_id', ['reward_product_id' => $record->reward_product_id]) : __('نفس المنتج'));

                        $pct = $record->reward_discount_percent;

                        return __('اشترِ :buy_qty من [:primary] واحصل على :get_qty من [:reward] بخصم :pct%', ['buy_qty' => $buyQty, 'primary' => $primary, 'get_qty' => $getQty, 'reward' => $reward, 'pct' => $pct]);
                    }),

                IconColumn::make('is_active')
                    ->label(__('مفعل'))
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductBundles::route('/'),
            'create' => CreateProductBundle::route('/create'),
            'edit' => EditProductBundle::route('/{record}/edit'),
        ];
    }
}
