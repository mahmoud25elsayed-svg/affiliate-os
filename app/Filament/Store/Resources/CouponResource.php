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

    protected static ?string $navigationLabel = 'الكوبونات والخصومات';

    protected static ?string $modelLabel = 'كوبون';

    protected static ?string $pluralModelLabel = 'الكوبونات';

    protected static \UnitEnum|string|null $navigationGroup = 'التسويق والمبيعات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات الكوبون')->schema([
                Grid::make(2)->schema([
                    TextInput::make('code')
                        ->label('كود الكوبون')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                    TextInput::make('name')
                        ->label('اسم الكوبون / الحملة')
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->label('نوع الخصم')
                        ->options([
                            Coupon::TYPE_PERCENTAGE => 'نسبة مئوية (%)',
                            Coupon::TYPE_FIXED => 'مبلغ ثابت (بالقروش)',
                            Coupon::TYPE_FREE_SHIPPING => 'شحن مجاني',
                        ])
                        ->required(),

                    TextInput::make('value_minor')
                        ->label('قيمة الخصم (نسبة % أو قروش)')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    TextInput::make('min_order_value_minor')
                        ->label('الحد الأدنى للطلب (بالقروش)')
                        ->numeric()
                        ->nullable(),

                    TextInput::make('max_discount_minor')
                        ->label('الحد الأقصى للخصم (بالقروش للنسبة)')
                        ->numeric()
                        ->nullable(),

                    TextInput::make('max_uses')
                        ->label('الحد الأقصى الإجمالي للاستخدام')
                        ->numeric()
                        ->nullable(),

                    TextInput::make('max_uses_per_customer')
                        ->label('الحد الأقصى لكل عميل')
                        ->numeric()
                        ->default(1)
                        ->required(),

                    DateTimePicker::make('starts_at')
                        ->label('تاريخ بدء السريان')
                        ->nullable(),

                    DateTimePicker::make('expires_at')
                        ->label('تاريخ انتهاء الصلاحية')
                        ->nullable(),

                    Toggle::make('is_active')
                        ->label('الكوبون نشط')
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
                    ->label('الكود')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Coupon::TYPE_PERCENTAGE => 'نسبة مئوية',
                        Coupon::TYPE_FIXED => 'مبلغ ثابت',
                        Coupon::TYPE_FREE_SHIPPING => 'شحن مجاني',
                        default => $state,
                    }),

                TextColumn::make('value_minor')
                    ->label('القيمة')
                    ->formatStateUsing(function (Coupon $record): string {
                        if ($record->type === Coupon::TYPE_PERCENTAGE) {
                            return "{$record->value_minor}%";
                        }
                        if ($record->type === Coupon::TYPE_FREE_SHIPPING) {
                            return 'شحن مجاني';
                        }

                        return Money::show($record->value_minor);
                    }),

                TextColumn::make('uses_count')
                    ->label('مرات الاستخدام')
                    ->formatStateUsing(fn (Coupon $record): string => "{$record->uses_count}".($record->max_uses ? " / {$record->max_uses}" : ''))
                    ->badge(),

                TextColumn::make('expires_at')
                    ->label('تاريخ الانتهاء')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('غير محدد')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('نشط')
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
