<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\InventoryBalanceResource\Pages\ListInventoryBalances;
use App\Models\InventoryBalance;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * @extends resource<InventoryBalance>
 */
class InventoryBalanceResource extends Resource
{
    protected static ?string $model = InventoryBalance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'أرصدة المخزون';

    protected static ?string $modelLabel = 'رصيد مخزون';

    protected static ?string $pluralModelLabel = 'أرصدة المخزون';

    protected static \UnitEnum|string|null $navigationGroup = 'المخزون والمستودعات';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('variant.sku')
                    ->label('كود المنتج (SKU)')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('on_hand')
                    ->label('المتوفر الفعلي (On Hand)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('reserved')
                    ->label('المحجوز (Reserved)')
                    ->numeric()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('available')
                    ->label('المتاح للبيع (Available)')
                    ->state(fn (InventoryBalance $record): int => $record->available)
                    ->numeric()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('low_stock_threshold')
                    ->label('حد الطلب الأدنى')
                    ->numeric(),

                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship('warehouse', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryBalances::route('/'),
        ];
    }
}
