<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Orders\Enums\OrderStatus;
use App\Filament\Store\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Store\Resources\OrderResource\Pages\ViewOrder;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

/**
 * @extends resource<Order>
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'الطلبات';

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'الطلبات';

    protected static \UnitEnum|string|null $navigationGroup = 'إدارة الطلبات';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer_name')
                    ->label('العميل')
                    ->state(function (Order $record): string {
                        /** @var OrderAddress|null $addr */
                        $addr = $record->shippingAddress;
                        if ($addr instanceof OrderAddress) {
                            $name = trim(($addr->first_name ?? '').' '.($addr->last_name ?? ''));
                            if ($name !== '') {
                                return $name;
                            }
                        }

                        return 'عميل زائر';
                    })
                    ->searchable(),

                TextColumn::make('customer_phone_e164')
                    ->label('الهاتف')
                    ->state(function (Order $record): string {
                        /** @var OrderAddress|null $addr */
                        $addr = $record->shippingAddress;
                        $phone = $addr instanceof OrderAddress ? $addr->contact_phone : null;
                        $attrPhone = $record->getAttribute('customer_phone_e164');

                        return $phone ?? (is_string($attrPhone) ? $attrPhone : '-');
                    })
                    ->searchable(),

                TextColumn::make('shippingAddress.city')
                    ->label('المحافظة / المدينة')
                    ->default('-'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state) => OrderStatus::tryFrom($state)?->color() ?? 'gray'),

                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->formatStateUsing(function (Order $record): string {
                        /** @var mixed $total */
                        $total = $record->total;
                        $value = is_object($total) && isset($total->value) ? (int) $total->value : (int) $total;

                        return number_format($value / 100, 2).' ج.م';
                    })
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'cod' ? 'الدفع عند الاستلام' : 'دفع إلكتروني')
                    ->color(fn (string $state) => $state === 'cod' ? 'warning' : 'success'),

                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('تصفية حسب الحالة')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])->all()),

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cod' => 'الدفع عند الاستلام (COD)',
                        'card' => 'دفع إلكتروني',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record) => static::getUrl('view', ['record' => $record])),

                Action::make('print_invoice')
                    ->label('فاتورة')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Order $record) => route('store.orders.invoice', ['reference' => $record->reference]), shouldOpenInNewTab: true),

                Action::make('print_waybill')
                    ->label('بوليصة')
                    ->icon('heroicon-o-truck')
                    ->color('gray')
                    ->url(fn (Order $record) => route('store.orders.waybill', ['reference' => $record->reference]), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
