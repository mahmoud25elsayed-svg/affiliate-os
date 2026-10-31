<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\InventoryBalanceResource\Pages;

use App\Domain\Inventory\Actions\InboundStockAction;
use App\Filament\Store\Resources\InventoryBalanceResource;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInventoryBalances extends ListRecords
{
    protected static string $resource = InventoryBalanceResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive_stock')
                ->label(__('استلام مخزون وارد'))
                ->icon('heroicon-o-arrow-down-tray')
                ->modalHeading(__('استلام كمية جديدة إلى المستودع'))
                ->schema([
                    Select::make('variant_id')
                        ->label(__('المنتج'))
                        ->options(fn (): array => InventoryBalanceResource::variantOptions())
                        ->searchable()
                        ->required(),
                    Select::make('warehouse_id')
                        ->label(__('المستودع'))
                        ->options(fn (): array => Warehouse::query()->active()->pluck('name', 'id')->all())
                        ->default(fn (): int => Warehouse::resolveDefault()->id)
                        ->required(),
                    TextInput::make('quantity')
                        ->label(__('الكمية المستلمة'))
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    Textarea::make('notes')->label(__('ملاحظات (رقم إذن الاستلام مثلاً)')),
                ])
                ->action(function (array $data): void {
                    app(InboundStockAction::class)->execute(
                        variantId: (int) $data['variant_id'],
                        quantity: (int) $data['quantity'],
                        warehouseId: (int) $data['warehouse_id'],
                        referenceType: 'manual_receipt',
                        actorId: (string) auth()->id(),
                        notes: isset($data['notes']) ? (string) $data['notes'] : null,
                    );

                    Notification::make()->title(__('تمت إضافة الكمية إلى المخزون'))->success()->send();
                }),
        ];
    }
}
