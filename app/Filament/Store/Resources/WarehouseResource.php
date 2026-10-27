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

    protected static ?string $navigationLabel = 'المستودعات';

    protected static ?string $modelLabel = 'مستودع';

    protected static ?string $pluralModelLabel = 'المستودعات';

    protected static \UnitEnum|string|null $navigationGroup = 'المخزون والمستودعات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات المستودع')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('اسم المستودع')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('code')
                        ->label('كود المستودع')
                        ->required()
                        ->maxLength(50)
                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                    Select::make('type')
                        ->label('نوع المستودع')
                        ->options([
                            'own' => 'مستودع خاص (Own)',
                            '3pl' => 'طرف ثالث (3PL Fulfillment)',
                            'supplier' => 'مستودع مورد (Supplier)',
                        ])
                        ->default('own')
                        ->required(),

                    TextInput::make('address')
                        ->label('العنوان')
                        ->maxLength(255),

                    Toggle::make('is_default')
                        ->label('المستودع الافتراضي')
                        ->default(false),

                    Toggle::make('is_active')
                        ->label('مستودع مفعّل')
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
                    ->label('اسم المستودع')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('الكود')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'own' => 'مستودع خاص',
                        '3pl' => 'طرف ثالث 3PL',
                        'supplier' => 'مورد',
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
                    ->label('افتراضي')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
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
