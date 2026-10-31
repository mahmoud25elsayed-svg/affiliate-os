<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\PlanResource\Pages\ListPlans;
use App\Models\Plan;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @extends resource<Plan>
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {

        return __('باقات الاشتراك');

    }

    public static function getModelLabel(): string
    {
        return __('باقة اشتراك');
    }

    public static function getPluralModelLabel(): string
    {
        return __('باقات الاشتراك');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('الفوترة والاشتراكات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('البيانات الأساسية'))->schema([
                TextInput::make('slug')
                    ->label(__('المعرّف الفريد'))
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('name_ar')
                    ->label(__('الاسم بالعربية'))
                    ->required(),

                TextInput::make('name_en')
                    ->label(__('الاسم بالإنجليزية'))
                    ->required(),

                TextInput::make('price_monthly')
                    ->label(__('السعر الشهري (بالقروش)'))
                    ->numeric()
                    ->required(),

                TextInput::make('price_yearly')
                    ->label(__('السعر السنوي (بالقروش)'))
                    ->numeric()
                    ->required(),

                TextInput::make('trial_days')
                    ->label(__('أيام الفترة التجريبية'))
                    ->numeric()
                    ->default(14)
                    ->required(),

                Toggle::make('is_active')
                    ->label(__('مفعلة'))
                    ->default(true),
            ])->columns(2),

            Section::make(__('حدود الموارد (Limits)'))->schema([
                KeyValue::make('limits')
                    ->label(__('الحدود (-1 لغير المحدود)'))
                    ->keyLabel(__('المورد'))
                    ->valueLabel(__('الحد الأقصى'))
                    ->reorderable(),
            ]),

            Section::make(__('الميزات المفعلة (Feature Flags)'))->schema([
                KeyValue::make('features')
                    ->label(__('الميزات'))
                    ->keyLabel(__('الميزة'))
                    ->valueLabel(__('مفعلة (1/0)'))
                    ->reorderable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ar')
                    ->label(__('اسم الباقة'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('المعرف'))
                    ->badge(),

                TextColumn::make('price_monthly')
                    ->label(__('السعر الشهري'))
                    ->formatStateUsing(fn (Plan $record) => $record->priceMonthlyMoney()->format()),

                TextColumn::make('price_yearly')
                    ->label(__('السعر السنوي'))
                    ->formatStateUsing(fn (Plan $record) => $record->priceYearlyMoney()->format()),

                TextColumn::make('trial_days')
                    ->label(__('أيام التجربة'))
                    ->suffix(__(' يوم')),

                IconColumn::make('is_active')
                    ->label(__('الحالة'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
        ];
    }
}
