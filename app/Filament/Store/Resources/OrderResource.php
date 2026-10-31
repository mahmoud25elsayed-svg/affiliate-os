<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Filament\Store\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Store\Resources\OrderResource\Pages\ViewOrder;
use App\Models\User;
use App\Support\Enums\ReasonCode;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

/**
 * @extends resource<Order>
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationLabel(): string
    {

        return __('الطلبات');

    }

    public static function getModelLabel(): string
    {
        return __('طلب');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الطلبات');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('إدارة الطلبات');
    }

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
                    ->label(__('رقم الطلب'))
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer_name')
                    ->label(__('العميل'))
                    ->state(function (Order $record): string {
                        /** @var OrderAddress|null $addr */
                        $addr = $record->shippingAddress;
                        if ($addr instanceof OrderAddress) {
                            $name = trim(($addr->first_name ?? '').' '.($addr->last_name ?? ''));
                            if ($name !== '') {
                                return $name;
                            }
                        }

                        return __('عميل زائر');
                    })
                    ->searchable(),

                TextColumn::make('customer_phone_e164')
                    ->label(__('الهاتف'))
                    ->state(function (Order $record): string {
                        /** @var OrderAddress|null $addr */
                        $addr = $record->shippingAddress;
                        $phone = $addr instanceof OrderAddress ? $addr->contact_phone : null;
                        $attrPhone = $record->getAttribute('customer_phone_e164');

                        return $phone ?? (is_string($attrPhone) ? $attrPhone : '-');
                    })
                    ->searchable(),

                TextColumn::make('shippingAddress.city')
                    ->label(__('المحافظة / المدينة'))
                    ->default('-'),

                TextColumn::make('affiliate.name')
                    ->label(__('المسوق'))
                    ->default(__('مباشر من المتجر'))
                    ->badge()
                    ->color(fn ($state) => $state === __('مباشر من المتجر') ? 'gray' : 'primary')
                    ->searchable(),

                TextColumn::make('quality_result')
                    ->label(__('فحص الجودة'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pass' => __('سليم (Pass)'),
                        'review' => __('مراجعة (Review)'),
                        'reject' => __('مرفوض (Reject)'),
                        default => __('غير محدد'),
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'pass' => 'success',
                        'review' => 'warning',
                        'reject' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state) => OrderStatus::tryFrom($state)?->color() ?? 'gray'),

                TextColumn::make('total')
                    ->label(__('الإجمالي'))
                    ->formatStateUsing(function (Order $record): string {
                        /** @var mixed $total */
                        $total = $record->total;
                        $value = is_object($total) && isset($total->value) ? (int) $total->value : (int) $total;

                        return Money::show($value);
                    })
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label(__('طريقة الدفع'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'cod' ? __('الدفع عند الاستلام') : __('دفع إلكتروني'))
                    ->color(fn (string $state) => $state === 'cod' ? 'warning' : 'success'),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الطلب'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('تصفية حسب الحالة'))
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])->all()),

                SelectFilter::make('quality_result')
                    ->label(__('طابور فحص الجودة'))
                    ->options([
                        'review' => __('طلبات قيد المراجعة (Review Queue)'),
                        'pass' => __('طلبات سليمة (Passed)'),
                        'reject' => __('طلبات مرفوضة (Rejected)'),
                    ]),

                SelectFilter::make('payment_method')
                    ->label(__('طريقة الدفع'))
                    ->options([
                        'cod' => __('الدفع عند الاستلام (COD)'),
                        'card' => __('دفع إلكتروني'),
                    ]),
            ])
            ->actions([
                Action::make('review_quality')
                    ->label(__('مراجعة الجودة'))
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn (Order $record) => $record->getAttribute('quality_result') === 'review' && $record->status === OrderStatus::PENDING_CONFIRMATION->value)
                    ->modalHeading(__('مراجعة فحص جودة الطلب (Quality Gate)'))
                    ->form(function (Order $record) {
                        /** @var mixed $rawMeta */
                        $rawMeta = $record->meta;
                        $reasons = is_array($rawMeta) && isset($rawMeta['quality_reasons']) ? (array) $rawMeta['quality_reasons'] : [];
                        $reasonsHtml = empty($reasons)
                            ? __('<p class="text-gray-500">لا توجد ملاحظات مسجلة.</p>')
                            : '<ul class="list-disc pr-4 space-y-1 text-sm text-amber-700">'.implode('', array_map(fn ($r) => '<li>'.e((string) $r).'</li>', $reasons)).'</ul>';

                        return [
                            Placeholder::make('flags_info')
                                ->label(__('أسباب تنبيه فحص الجودة'))
                                ->content(new HtmlString($reasonsHtml)),

                            Select::make('decision')
                                ->label(__('قرار التاجر'))
                                ->options([
                                    'confirm' => __('اعتماد وتأكيد الطلب (Confirmed)'),
                                    'reject' => __('رفض الطلب لعدم صلاحية الجودة (Quality Rejected)'),
                                ])
                                ->required(),

                            Textarea::make('notes')
                                ->label(__('ملاحظات القرار')),
                        ];
                    })
                    ->action(function (Order $record, array $data, OrderTransitionService $transitionService): void {
                        /** @var User $user */
                        $user = auth()->user();
                        $actor = OrderActor::staff($user);

                        $record->update([
                            'quality_gate_reviewed_at' => now(),
                            'quality_gate_reviewed_by' => $user->id,
                        ]);

                        if ($data['decision'] === 'confirm') {
                            $transitionService->transition(
                                order: $record,
                                to: OrderStatus::CONFIRMED,
                                actor: $actor,
                                note: $data['notes'] ?? __('تم تأكيد واعتماد الطلب من طابور مراجعة الجودة')
                            );
                            Notification::make()->title(__('تم اعتماد وتأكيد الطلب بنجاح'))->success()->send();
                        } else {
                            $transitionService->transition(
                                order: $record,
                                to: OrderStatus::REJECTED_QUALITY,
                                reason: ReasonCode::SUSPECTED_FRAUD,
                                actor: $actor,
                                note: $data['notes'] ?? __('تم رفض الطلب بناءً على نتائج فحص الجودة')
                            );
                            Notification::make()->title(__('تم رفض الطلب وإلغاؤه'))->warning()->send();
                        }
                    }),

                Action::make('view')
                    ->label(__('عرض'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record) => static::getUrl('view', ['record' => $record])),

                Action::make('print_invoice')
                    ->label(__('فاتورة'))
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Order $record) => route('store.orders.invoice', ['reference' => $record->reference]), shouldOpenInNewTab: true),

                Action::make('print_waybill')
                    ->label(__('بوليصة'))
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
