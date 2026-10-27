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

    protected static ?string $navigationLabel = 'صفقة اليوم';

    protected static ?string $modelLabel = 'صفقة يومية';

    protected static ?string $pluralModelLabel = 'صفقات اليوم';

    protected static \UnitEnum|string|null $navigationGroup = 'التسويق والمبيعات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات صفقة اليوم')->schema([
                Grid::make(2)->schema([
                    Select::make('product_id')
                        ->label('المنتج')
                        ->options(function () {
                            return Product::all()->mapWithKeys(function (Product $prod) {
                                return [$prod->id => (string) ($prod->translateAttribute('name') ?? "منتج #{$prod->id}")];
                            });
                        })
                        ->searchable()
                        ->required(),

                    DatePicker::make('deal_date')
                        ->label('تاريخ تطبيق الصفقة')
                        ->required()
                        ->default(now()),

                    Select::make('discount_type')
                        ->label('نوع الخصم')
                        ->options([
                            'percentage' => 'نسبة مئوية (%)',
                            'fixed' => 'مبلغ ثابت (بالقروش - Minor Units)',
                        ])
                        ->required()
                        ->default('percentage'),

                    TextInput::make('discount_value')
                        ->label('قيمة الخصم')
                        ->helperText('إذا كانت النسبة 20% ضع 20، وإذا كان مبلغ ثابت بالقروش مثل 5000 ضع 5000')
                        ->numeric()
                        ->required(),

                    TextInput::make('banner_title')
                        ->label('عنوان البانر الإعلاني (اختياري)')
                        ->maxLength(255)
                        ->placeholder('مثال: صفقة اليوم الحصرية على أفضل سماعة بلوتوث!'),

                    TextInput::make('badge_text')
                        ->label('نص الشارة الترويجية')
                        ->maxLength(50)
                        ->default('صفقة اليوم'),

                    Toggle::make('is_active')
                        ->label('مفعل')
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
                    ->label('المنتج')
                    ->formatStateUsing(function (DailyDeal $record): string {
                        return (string) ($record->product?->translateAttribute('name') ?? "منتج #{$record->product_id}");
                    })
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('deal_date')
                    ->label('تاريخ الصفقة')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('discount')
                    ->label('قيمة الخصم')
                    ->state(function (DailyDeal $record): string {
                        if ($record->discount_type === 'percentage') {
                            return "{$record->discount_value}%";
                        }

                        return Money::fromMinor($record->discount_value)->format('ar');
                    })
                    ->badge()
                    ->color('warning'),

                IconColumn::make('is_active')
                    ->label('مفعل')
                    ->boolean(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->state(function (DailyDeal $record): string {
                        $today = now()->toDateString();
                        $dealDate = $record->deal_date->toDateString();

                        if (! $record->is_active) {
                            return 'معطل';
                        }
                        if ($dealDate === $today) {
                            return 'متاح اليوم ⭐';
                        }
                        if ($dealDate > $today) {
                            return 'مجدول مستقبلاً';
                        }

                        return 'منتهي';
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
