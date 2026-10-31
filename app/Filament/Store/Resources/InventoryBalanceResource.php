<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Filament\Store\Resources\InventoryBalanceResource\Pages\ListInventoryBalances;
use App\Models\InventoryBalance;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * @extends resource<InventoryBalance>
 */
class InventoryBalanceResource extends Resource
{
    protected static ?string $model = InventoryBalance::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';

    public static function getNavigationLabel(): string
    {

        return __('أرصدة المخزون');

    }

    public static function getModelLabel(): string
    {
        return __('رصيد مخزون');
    }

    public static function getPluralModelLabel(): string
    {
        return __('أرصدة المخزون');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('المخزون والمستودعات');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label(__('المستودع'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('variant.sku')
                    ->label(__('كود المنتج (SKU)'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('on_hand')
                    ->label(__('المتوفر الفعلي (On Hand)'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('reserved')
                    ->label(__('المحجوز (Reserved)'))
                    ->numeric()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('available')
                    ->label(__('المتاح للبيع (Available)'))
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
                    ->label(__('حد الطلب الأدنى'))
                    ->numeric(),

                TextColumn::make('updated_at')
                    ->label(__('آخر تحديث'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label(__('المستودع'))
                    ->relationship('warehouse', 'name'),
            ])
            ->actions([
                Action::make('adjust')
                    ->label(__('تسوية جرد'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading(__('تسوية الرصيد الفعلي بعد الجرد'))
                    ->schema([
                        TextInput::make('new_on_hand')
                            ->label(__('الكمية الفعلية بعد الجرد'))
                            ->integer()
                            ->minValue(fn (InventoryBalance $record): int => $record->reserved)
                            ->default(fn (InventoryBalance $record): int => $record->on_hand)
                            ->helperText(fn (InventoryBalance $record): string => __('لا يمكن أن تقل عن الكمية المحجوزة لطلبات قائمة (:reserved).', ['reserved' => $record->reserved]))
                            ->required(),
                        Textarea::make('notes')->label(__('سبب التسوية'))->required(),
                    ])
                    ->action(function (InventoryBalance $record, array $data): void {
                        app(AdjustStockAction::class)->execute(
                            variantId: (int) $record->product_variant_id,
                            warehouseId: (int) $record->warehouse_id,
                            newOnHand: (int) $data['new_on_hand'],
                            notes: (string) $data['notes'],
                            actorId: (string) auth()->id(),
                        );

                        Notification::make()->title(__('تم تحديث رصيد المخزون'))->success()->send();
                    }),
            ]);
    }

    /**
     * @return array<int, string> variant id => "SKU — product name"
     */
    public static function variantOptions(): array
    {
        return ProductVariant::query()->with('product')->get()
            ->mapWithKeys(fn (ProductVariant $v): array => [
                (int) $v->id => trim(($v->sku ?? '#'.$v->id).' — '.($v->product instanceof Product ? ($v->product->translateAttribute('name') ?? '') : '')),
            ])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryBalances::route('/'),
        ];
    }
}
