<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\CouponResource\Pages\ListCoupons;
use App\Models\Coupon;
use App\Support\Money;
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

/**
 * @extends resource<Coupon>
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationLabel(): string
    {

        return __('الكوبونات والخصومات');

    }

    public static function getModelLabel(): string
    {
        return __('كوبون');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الكوبونات');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('التسويق والمبيعات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('معلومات الكوبون'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('code')
                        ->label(__('كود الكوبون'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                    TextInput::make('name')
                        ->label(__('اسم الكوبون / الحملة'))
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->label(__('نوع الخصم'))
                        ->options([
                            Coupon::TYPE_PERCENTAGE => __('نسبة مئوية (%)'),
                            Coupon::TYPE_FIXED => __('مبلغ ثابت (بالقروش)'),
                            Coupon::TYPE_FREE_SHIPPING => __('شحن مجاني'),
                        ])
                        ->required(),

                    TextInput::make('value_minor')
                        ->label(__('قيمة الخصم (نسبة % أو قروش)'))
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make('min_order_value_minor')
                        ->label(__('الحد الأدنى للطلب (بالقروش)'))
                        ->numeric()
                        ->nullable(),

                    TextInput::make('max_discount_minor')
                        ->label(__('الحد الأقصى للخصم (بالقروش للنسبة)'))
                        ->numeric()
                        ->nullable(),

                    TextInput::make('max_uses')
                        ->label(__('الحد الأقصى الإجمالي للاستخدام'))
                        ->numeric()
                        ->nullable(),

                    TextInput::make('max_uses_per_customer')
                        ->label(__('الحد الأقصى لكل عميل'))
                        ->numeric()
                        ->default(1)
                        ->required(),

                    DateTimePicker::make('starts_at')
                        ->label(__('تاريخ بدء السريان'))
                        ->nullable(),

                    DateTimePicker::make('expires_at')
                        ->label(__('تاريخ انتهاء الصلاحية'))
                        ->nullable(),

                    Toggle::make('is_active')
                        ->label(__('الكوبون نشط'))
                        ->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('الكود'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('النوع'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Coupon::TYPE_PERCENTAGE => __('نسبة مئوية'),
                        Coupon::TYPE_FIXED => __('مبلغ ثابت'),
                        Coupon::TYPE_FREE_SHIPPING => __('شحن مجاني'),
                        default => $state,
                    }),

                TextColumn::make('value_minor')
                    ->label(__('القيمة'))
                    ->formatStateUsing(function (Coupon $record): string {
                        if ($record->type === Coupon::TYPE_PERCENTAGE) {
                            return "{$record->value_minor}%";
                        }
                        if ($record->type === Coupon::TYPE_FREE_SHIPPING) {
                            return __('شحن مجاني');
                        }

                        return Money::show($record->value_minor);
                    }),

                TextColumn::make('uses_count')
                    ->label(__('مرات الاستخدام'))
                    ->formatStateUsing(fn (Coupon $record): string => "{$record->uses_count}".($record->max_uses ? " / {$record->max_uses}" : ''))
                    ->badge(),

                TextColumn::make('expires_at')
                    ->label(__('تاريخ الانتهاء'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder(__('غير محدد'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('نشط'))
                    ->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
        ];
    }
}
