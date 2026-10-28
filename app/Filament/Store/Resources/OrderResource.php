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

                TextColumn::make('affiliate.name')
                    ->label('المسوق')
                    ->default('مباشر من المتجر')
                    ->badge()
                    ->color(fn ($state) => $state === 'مباشر من المتجر' ? 'gray' : 'primary')
                    ->searchable(),

                TextColumn::make('quality_result')
                    ->label('فحص الجودة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pass' => 'سليم (Pass)',
                        'review' => 'مراجعة (Review)',
                        'reject' => 'مرفوض (Reject)',
                        default => 'غير محدد',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'pass' => 'success',
                        'review' => 'warning',
                        'reject' => 'danger',
                        default => 'gray',
                    }),

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

                SelectFilter::make('quality_result')
                    ->label('طابور فحص الجودة')
                    ->options([
                        'review' => 'طلبات قيد المراجعة (Review Queue)',
                        'pass' => 'طلبات سليمة (Passed)',
                        'reject' => 'طلبات مرفوضة (Rejected)',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cod' => 'الدفع عند الاستلام (COD)',
                        'card' => 'دفع إلكتروني',
                    ]),
            ])
            ->actions([
                Action::make('review_quality')
                    ->label('مراجعة الجودة')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn (Order $record) => $record->getAttribute('quality_result') === 'review' && $record->status === OrderStatus::PENDING_CONFIRMATION->value)
                    ->modalHeading('مراجعة فحص جودة الطلب (Quality Gate)')
                    ->form(function (Order $record) {
                        /** @var mixed $rawMeta */
                        $rawMeta = $record->meta;
                        $reasons = is_array($rawMeta) && isset($rawMeta['quality_reasons']) ? (array) $rawMeta['quality_reasons'] : [];
                        $reasonsHtml = empty($reasons)
                            ? '<p class="text-gray-500">لا توجد ملاحظات مسجلة.</p>'
                            : '<ul class="list-disc pr-4 space-y-1 text-sm text-amber-700">'.implode('', array_map(fn ($r) => '<li>'.e((string) $r).'</li>', $reasons)).'</ul>';

                        return [
                            Placeholder::make('flags_info')
                                ->label('أسباب تنبيه فحص الجودة')
                                ->content(new HtmlString($reasonsHtml)),

                            Select::make('decision')
                                ->label('قرار التاجر')
                                ->options([
                                    'confirm' => 'اعتماد وتأكيد الطلب (Confirmed)',
                                    'reject' => 'رفض الطلب لعدم صلاحية الجودة (Quality Rejected)',
                                ])
                                ->required(),

                            Textarea::make('notes')
                                ->label('ملاحظات القرار'),
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
                                note: $data['notes'] ?? 'تم تأكيد واعتماد الطلب من طابور مراجعة الجودة'
                            );
                            Notification::make()->title('تم اعتماد وتأكيد الطلب بنجاح')->success()->send();
                        } else {
                            $transitionService->transition(
                                order: $record,
                                to: OrderStatus::REJECTED_QUALITY,
                                reason: ReasonCode::SUSPECTED_FRAUD,
                                actor: $actor,
                                note: $data['notes'] ?? 'تم رفض الطلب بناءً على نتائج فحص الجودة'
                            );
                            Notification::make()->title('تم رفض الطلب وإلغاؤه')->warning()->send();
                        }
                    }),

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
