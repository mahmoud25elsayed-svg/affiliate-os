<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\DailyDealResource\Pages\CreateDailyDeal;
use App\Filament\Store\Resources\DailyDealResource\Pages\EditDailyDeal;
use App\Filament\Store\Resources\DailyDealResource\Pages\ListDailyDeals;
use App\Models\DailyDeal;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Product;

/**
 * @extends resource<DailyDeal>
 */
class DailyDealResource extends Resource
{
    protected static ?string $model = DailyDeal::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    public static function getNavigationLabel(): string
    {

        return __('صفقة اليوم');

    }

    public static function getModelLabel(): string
    {
        return __('صفقة يومية');
    }

    public static function getPluralModelLabel(): string
    {
        return __('صفقات اليوم');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('التسويق والمبيعات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('معلومات صفقة اليوم'))->schema([
                Grid::make(2)->schema([
                    Select::make('product_id')
                        ->label(__('المنتج'))
                        ->options(function () {
                            return Product::all()->mapWithKeys(function (Product $prod) {
                                return [$prod->id => (string) ($prod->translateAttribute('name') ?? __('منتج #:id', ['id' => $prod->id]))];
                            });
                        })
                        ->searchable()
                        ->required(),

                    DatePicker::make('deal_date')
                        ->label(__('تاريخ تطبيق الصفقة'))
                        ->required()
                        ->default(now()),

                    Select::make('discount_type')
                        ->label(__('نوع الخصم'))
                        ->options([
                            'percentage' => __('نسبة مئوية (%)'),
                            'fixed' => __('مبلغ ثابت (بالقروش - Minor Units)'),
                        ])
                        ->required()
                        ->default('percentage'),

                    TextInput::make('discount_value')
                        ->label(__('قيمة الخصم'))
                        ->helperText(__('إذا كانت النسبة 20% ضع 20، وإذا كان مبلغ ثابت بالقروش مثل 5000 ضع 5000'))
                        ->numeric()
                        ->required(),

                    TextInput::make('banner_title')
                        ->label(__('عنوان البانر الإعلاني (اختياري)'))
                        ->maxLength(255)
                        ->placeholder(__('مثال: صفقة اليوم الحصرية على أفضل سماعة بلوتوث!')),

                    TextInput::make('badge_text')
                        ->label(__('نص الشارة الترويجية'))
                        ->maxLength(50)
                        ->default(__('صفقة اليوم')),

                    Toggle::make('is_active')
                        ->label(__('مفعل'))
                        ->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.id')
                    ->label(__('المنتج'))
                    ->formatStateUsing(function (DailyDeal $record): string {
                        return (string) ($record->product?->translateAttribute('name') ?? __('منتج #:product_id', ['product_id' => $record->product_id]));
                    })
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('deal_date')
                    ->label(__('تاريخ الصفقة'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('discount')
                    ->label(__('قيمة الخصم'))
                    ->state(function (DailyDeal $record): string {
                        if ($record->discount_type === 'percentage') {
                            return "{$record->discount_value}%";
                        }

                        return Money::fromMinor($record->discount_value)->format();
                    })
                    ->badge()
                    ->color('warning'),

                IconColumn::make('is_active')
                    ->label(__('مفعل'))
                    ->boolean(),

                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->state(function (DailyDeal $record): string {
                        $today = now()->toDateString();
                        $dealDate = $record->deal_date->toDateString();

                        if (! $record->is_active) {
                            return __('معطل');
                        }
                        if ($dealDate === $today) {
                            return __('متاح اليوم ⭐');
                        }
                        if ($dealDate > $today) {
                            return __('مجدول مستقبلاً');
                        }

                        return __('منتهي');
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'متاح اليوم ⭐' => 'success',
                        'مجدول مستقبلاً' => 'info',
                        'منتهي' => 'gray',
                        default => 'danger',
                    }),
            ])
            ->defaultSort('deal_date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product.media', 'variant.prices']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyDeals::route('/'),
            'create' => CreateDailyDeal::route('/create'),
            'edit' => EditDailyDeal::route('/{record}/edit'),
        ];
    }
}
