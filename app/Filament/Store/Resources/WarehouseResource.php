<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\WarehouseResource\Pages\ListWarehouses;
use App\Models\Warehouse;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
 * @extends resource<Warehouse>
 */
class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    public static function getNavigationLabel(): string
    {

        return __('المستودعات');

    }

    public static function getModelLabel(): string
    {
        return __('مستودع');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المستودعات');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('المخزون والمستودعات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('معلومات المستودع'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('اسم المستودع'))
                        ->required()
                        ->maxLength(100),

                    TextInput::make('code')
                        ->label(__('كود المستودع'))
                        ->required()
                        ->maxLength(50)
                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                    Select::make('type')
                        ->label(__('نوع المستودع'))
                        ->options([
                            'own' => __('مستودع خاص (Own)'),
                            '3pl' => __('طرف ثالث (3PL Fulfillment)'),
                            'supplier' => __('مستودع مورد (Supplier)'),
                        ])
                        ->default('own')
                        ->required(),

                    TextInput::make('address')
                        ->label(__('العنوان'))
                        ->maxLength(255),

                    Toggle::make('is_default')
                        ->label(__('المستودع الافتراضي'))
                        ->default(false),

                    Toggle::make('is_active')
                        ->label(__('مستودع مفعّل'))
                        ->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('اسم المستودع'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label(__('الكود'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('النوع'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'own' => __('مستودع خاص'),
                        '3pl' => __('طرف ثالث 3PL'),
                        'supplier' => __('مورد'),
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'own' => 'success',
                        '3pl' => 'info',
                        'supplier' => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_default')
                    ->label(__('افتراضي'))
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label(__('نشط'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الإنشاء'))
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
        ];
    }
}
