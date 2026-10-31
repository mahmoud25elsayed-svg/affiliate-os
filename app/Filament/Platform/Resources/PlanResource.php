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

    protected static ?string $navigationLabel = 'باقات الاشتراك';

    protected static ?string $modelLabel = 'باقة اشتراك';

    protected static ?string $pluralModelLabel = 'باقات الاشتراك';

    protected static \UnitEnum|string|null $navigationGroup = 'الفوترة والاشتراكات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('البيانات الأساسية')->schema([
                TextInput::make('slug')
                    ->label('المعرّف الفريد')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->required(),

                TextInput::make('name_en')
                    ->label('الاسم بالإنجليزية')
                    ->required(),

                TextInput::make('price_monthly')
                    ->label('السعر الشهري (بالقروش)')
                    ->numeric()
                    ->required(),

                TextInput::make('price_yearly')
                    ->label('السعر السنوي (بالقروش)')
                    ->numeric()
                    ->required(),

                TextInput::make('trial_days')
                    ->label('أيام الفترة التجريبية')
                    ->numeric()
                    ->default(14)
                    ->required(),

                Toggle::make('is_active')
                    ->label('مفعلة')
                    ->default(true),
            ])->columns(2),

            Section::make('حدود الموارد (Limits)')->schema([
                KeyValue::make('limits')
                    ->label('الحدود (-1 لغير المحدود)')
                    ->keyLabel('المورد')
                    ->valueLabel('الحد الأقصى')
                    ->reorderable(),
            ]),

            Section::make('الميزات المفعلة (Feature Flags)')->schema([
                KeyValue::make('features')
                    ->label('الميزات')
                    ->keyLabel('الميزة')
                    ->valueLabel('مفعلة (1/0)')
                    ->reorderable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ar')
                    ->label('اسم الباقة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('المعرف')
                    ->badge(),

                TextColumn::make('price_monthly')
                    ->label('السعر الشهري')
                    ->formatStateUsing(fn (Plan $record) => $record->priceMonthlyMoney()->format()),

                TextColumn::make('price_yearly')
                    ->label('السعر السنوي')
                    ->formatStateUsing(fn (Plan $record) => $record->priceYearlyMoney()->format()),

                TextColumn::make('trial_days')
                    ->label('أيام التجربة')
                    ->suffix(' يوم'),

                IconColumn::make('is_active')
                    ->label('الحالة')
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
