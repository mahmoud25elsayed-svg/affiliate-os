<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Catalog\Services\ProductService;
use App\Filament\Store\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Store\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Store\Resources\ProductResource\Pages\ListProducts;
use App\Models\WholesalePrice;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * @extends resource<Product>
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationLabel(): string
    {

        return __('المنتجات');

    }

    public static function getModelLabel(): string
    {
        return __('منتج');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المنتجات');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('إدارة الكتالوج');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('معلومات المنتج الأساسية'))->schema([
                TextInput::make('name_ar')
                    ->label(__('اسم المنتج (بالعربية)'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_en')
                    ->label(__('اسم المنتج (بالإنجليزية)'))
                    ->maxLength(255),

                TextInput::make('sku')
                    ->label(__('رمز المنتج (SKU)'))
                    ->required()
                    ->maxLength(100),

                Select::make('brand_id')
                    ->label(__('العلامة التجارية (Brand)'))
                    ->options(fn () => Brand::pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                Select::make('collection_ids')
                    ->label(__('الفئات والتصنيفات'))
                    ->multiple()
                    ->options(fn () => Collection::all()->mapWithKeys(function ($c) {
                        /** @var Collection $c */
                        return [$c->id => (string) ($c->translateAttribute('name') ?? __('فئة #').$c->id)];
                    }))
                    ->searchable(),

                Select::make('status')
                    ->label(__('حالة المنتج'))
                    ->options([
                        WholesalePrice::STATUS_ACTIVE => __('نشط ومتاح للطلب'),
                        WholesalePrice::STATUS_DRAFT => __('مسودة (Draft)'),
                        WholesalePrice::STATUS_PAUSED => __('موقوف مؤقتًا'),
                        WholesalePrice::STATUS_ARCHIVED => __('مؤرشف'),
                    ])
                    ->default(WholesalePrice::STATUS_ACTIVE)
                    ->required(),

                Textarea::make('pause_reason')
                    ->label(__('سبب الإيقاف (مطلوب عند الإيقاف المؤقت)'))
                    ->rows(2)
                    ->nullable(),
            ])->columns(2),

            Section::make(__('التسعير وسياسة البيع للأفلييت (بعملة المتجر)'))->schema([
                static::moneyInput('retail_price', __('سعر البيع المقترح للعميل'))
                    ->required()
                    ->helperText(__('مثال: 250 أو 249.99')),

                static::moneyInput('compare_at_price', __('السعر قبل الخصم (اختياري)'))
                    ->nullable(),

                static::moneyInput('wholesale_price', __('سعر الجملة للمسوق (Wholesale)'))
                    ->required()
                    ->helperText(__('سعر تكلفة المنتج على التاجر للأفلييت')),

                static::moneyInput('min_price', __('الحد الأدنى لسعر بيع المسوق (Min)'))
                    ->required()
                    ->helperText(__('أقل سعر يُسمح للمسوق بالبيع به (يجب أن يكون ≥ الجملة)')),

                static::moneyInput('max_price', __('الحد الأقصى لسعر بيع المسوق (Max)'))
                    ->required()
                    ->helperText(__('أعلى سعر يُسمح للمسوق بالبيع به (يجب أن يكون ≥ الأدنى)')),
            ])->columns(3),

            Section::make(__('المخزون الافتتاحي'))->schema([
                TextInput::make('opening_stock')
                    ->label(__('الكمية المتوفرة الآن'))
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->helperText(__('تُضاف إلى المستودع الافتراضي لتصبح قابلة للبيع والحجز. الإضافات اللاحقة من صفحة أرصدة المخزون.')),
            ])->visibleOn('create'),

            Section::make(__('المنتجات الرقمية (Digital Products)'))->schema([
                Toggle::make('is_digital')
                    ->label(__('منتج رقمي (ملف قابل للتحميل بعد الدفع)'))
                    ->default(false),

                FileUpload::make('digital_file_path')
                    ->label(__('الملف الرقمي'))
                    ->disk('public')
                    ->directory('digital-products')
                    ->visible(fn ($get) => (bool) $get('is_digital')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('اسم المنتج'))
                    ->getStateUsing(fn (Product $record) => $record->translateAttribute('name') ?? __('منتج #').$record->id)
                    ->searchable(query: function (Builder $query, string $search) {
                        return $query->where('attribute_data', 'like', "%{$search}%");
                    })
                    ->weight('bold'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->getStateUsing(function (Product $record) {
                        $variant = static::getFirstVariant($record);

                        return $variant ? $variant->sku : '-';
                    }),

                TextColumn::make('retail_price')
                    ->label(__('سعر التجزئة'))
                    ->getStateUsing(function (Product $record) {
                        $variant = static::getFirstVariant($record);
                        /** @var Price|null $price */
                        $price = $variant?->prices->first();

                        return $price ? (string) $price->price->formatted() : '-';
                    })
                    ->weight('bold'),

                TextColumn::make('wholesale_price')
                    ->label(__('سعر الجملة'))
                    ->getStateUsing(function (Product $record) {
                        $pricing = static::getWholesalePrice($record);

                        return $pricing ? $pricing->wholesalePriceMoney()->format() : '-';
                    }),

                TextColumn::make('affiliate_range')
                    ->label(__('نطاق بيع الأفلييت'))
                    ->getStateUsing(function (Product $record) {
                        $pricing = static::getWholesalePrice($record);
                        if (! $pricing) {
                            return '-';
                        }

                        return $pricing->minPriceMoney()->format().' - '.$pricing->maxPriceMoney()->format();
                    }),

                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    ->getStateUsing(function (Product $record) {
                        $pricing = static::getWholesalePrice($record);

                        return $pricing ? $pricing->status : $record->status;
                    })
                    ->colors([
                        'success' => WholesalePrice::STATUS_ACTIVE,
                        'gray' => WholesalePrice::STATUS_DRAFT,
                        'warning' => WholesalePrice::STATUS_PAUSED,
                        'danger' => WholesalePrice::STATUS_ARCHIVED,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        WholesalePrice::STATUS_ACTIVE => __('نشط'),
                        WholesalePrice::STATUS_DRAFT => __('مسودة'),
                        WholesalePrice::STATUS_PAUSED => __('موقوف'),
                        WholesalePrice::STATUS_ARCHIVED => __('مؤرشف'),
                        default => $state,
                    }),
            ])
            ->recordActions([
                Action::make('pause_product')
                    ->label(__('إيقاف مؤقت'))
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(function (Product $record) {
                        $pricing = static::getWholesalePrice($record);

                        return $pricing ? $pricing->isActive() : true;
                    })
                    ->schema([
                        TextInput::make('reason')
                            ->label(__('سبب الإيقاف المؤقت'))
                            ->required()
                            ->placeholder(__('مثال: نفاد المخزون مؤقتًا من المورد')),
                    ])
                    ->action(function (Product $record, array $data, ProductService $service) {
                        $service->updateStatus($record, WholesalePrice::STATUS_PAUSED, (string) $data['reason']);
                        Notification::make()->title(__('تم إيقاف المنتج مؤقتًا'))->warning()->send();
                    }),

                Action::make('activate_product')
                    ->label(__('تفعيل المنتج'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Product $record) {
                        $pricing = static::getWholesalePrice($record);

                        return $pricing ? $pricing->isPaused() : false;
                    })
                    ->action(function (Product $record, ProductService $service) {
                        $service->updateStatus($record, WholesalePrice::STATUS_ACTIVE);
                        Notification::make()->title(__('تم تفعيل المنتج بنجاح'))->success()->send();
                    }),
            ]);
    }

    protected static function getFirstVariant(Product $record): ?ProductVariant
    {
        /** @var ProductVariant|null $variant */
        $variant = $record->variants->first();

        return $variant;
    }

    protected static function getWholesalePrice(Product $record): ?WholesalePrice
    {
        $variant = static::getFirstVariant($record);
        if (! $variant) {
            return null;
        }

        /** @var WholesalePrice|null $pricing */
        $pricing = $variant->getRelationValue('wholesalePrice');

        return $pricing;
    }

    /**
     * Eager loading to completely prevent N+1 queries.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'variants.wholesalePrice',
                'variants.prices',
                'brand',
                'productType',
            ]);
    }

    /**
     * Price field the merchant fills in pounds ("250.50") while the form state stays in integer
     * piasters, so the create/edit pages and ProductService keep working in minor units.
     */
    protected static function moneyInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->suffix(Money::symbol())
            ->inputMode('decimal')
            ->rule('regex:/^\d{1,7}(\.\d{1,2})?$/')
            ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state)
                ? Money::fromMinor((int) $state)->toDecimalString()
                : null)
            ->dehydrateStateUsing(fn (mixed $state): ?int => (is_numeric($state) || (is_string($state) && trim($state) !== ''))
                ? Money::fromDecimalString(trim((string) $state))->amount
                : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
