<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\OrderResource\Pages;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitions;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Filament\Store\Resources\OrderResource;
use App\Models\OrderEvent;
use App\Support\Enums\ReasonCode;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Lunar\Models\Order;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.store.resources.order-resource.pages.view-order';

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        /** @var Order $record */
        $record = $this->getRecord();
        $currentStatus = OrderStatus::tryFrom((string) $record->status) ?? OrderStatus::SUBMITTED;

        $user = Auth::user();
        $actor = $user !== null ? OrderActor::staff($user) : OrderActor::system();

        $actions = [
            Action::make('print_invoice')
                ->label('طباعة الفاتورة')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('store.orders.invoice', ['reference' => $record->reference]), shouldOpenInNewTab: true),

            Action::make('print_waybill')
                ->label('بوليصة الشحن')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(route('store.orders.waybill', ['reference' => $record->reference]), shouldOpenInNewTab: true),
        ];

        // Dynamic State Machine Actions
        $allowedTargets = OrderTransitions::getAllowedTransitions($currentStatus, $actor);

        foreach ($allowedTargets as $targetStatus) {
            $requiresReason = OrderTransitions::requiresReason($currentStatus, $targetStatus);
            $actionLabel = $this->getTransitionActionLabel($targetStatus);

            $action = Action::make('transition_to_'.$targetStatus->value)
                ->label($actionLabel)
                ->color($this->getTransitionActionColor($targetStatus))
                ->icon($this->getTransitionActionIcon($targetStatus))
                ->requiresConfirmation()
                ->modalHeading('تغيير حالة الطلب إلى: '.$targetStatus->label())
                ->modalSubmitActionLabel('تنفيذ التغيير');

            if ($requiresReason) {
                $reasonOptions = collect(ReasonCode::cases())->mapWithKeys(fn (ReasonCode $r) => [$r->value => $r->label()])->all();

                $action->schema([
                    Select::make('reason_code')
                        ->label('سبب الإجراء (إلزامي)')
                        ->options($reasonOptions)
                        ->required()
                        ->searchable(),

                    Textarea::make('note')
                        ->label('ملاحظات إضافية')
                        ->placeholder('اكتب تفاصيل أو أسباب إضافية هنا...'),
                ]);
            } else {
                $action->schema([
                    Textarea::make('note')
                        ->label('ملاحظات اختيارية')
                        ->placeholder('اكتب أي ملاحظة إن وجدت...'),
                ]);
            }

            $action->action(function (array $data) use ($record, $targetStatus, $actor): void {
                $reason = isset($data['reason_code']) ? ReasonCode::tryFrom((string) $data['reason_code']) : null;
                $note = isset($data['note']) && is_string($data['note']) ? $data['note'] : null;

                /** @var OrderTransitionService $service */
                $service = app(OrderTransitionService::class);
                $service->transition($record, $targetStatus, $reason, $actor, [], $note);

                Notification::make()
                    ->title('تم تحديث حالة الطلب بنجاح إلى: '.$targetStatus->label())
                    ->success()
                    ->send();

                // Header actions are built from the status at render time; reload so the next
                // allowed transitions appear instead of the ones for the previous status.
                $this->redirect(OrderResource::getUrl('view', ['record' => $record]));
            });

            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Order $record */
        $record = $this->getRecord();
        $record->load(['lines.purchasable', 'shippingAddress']);

        $events = OrderEvent::query()
            ->where('order_id', $record->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'order' => $record,
            'events' => $events,
        ];
    }

    protected function getTransitionActionLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::CONFIRMED => 'تأكيد الطلب',
            OrderStatus::CANCELLED_BY_CUSTOMER => 'إلغاء الطلب',
            OrderStatus::UNREACHABLE => 'تعذر الاتصال',
            OrderStatus::CANCELLED_NO_ANSWER => 'إلغاء لعدم الرد',
            OrderStatus::RESERVED => 'حجز المخزون',
            OrderStatus::OUT_OF_STOCK => 'نفاد المخزون',
            OrderStatus::PACKED => 'تجهيز وتعبئة',
            OrderStatus::SHIPPED => 'تسليم للشحن',
            OrderStatus::OUT_FOR_DELIVERY => 'خرج للتوصيل',
            OrderStatus::DELIVERED => 'تم التسليم والتحصيل',
            OrderStatus::DELIVERY_FAILED => 'تعذر التسليم',
            OrderStatus::RETURNING => 'شحن المرتجع',
            OrderStatus::RETURN_REQUESTED => 'طلب استرجاع',
            OrderStatus::RETURNED => 'استلام المرتجع بالمخزن',
            OrderStatus::SETTLED => 'تسوية مالية',
            OrderStatus::PENDING_CONFIRMATION => 'إعادة لانتظار التأكيد',
            OrderStatus::REJECTED_QUALITY => 'رفض فحص الجودة',
            default => $status->label(),
        };
    }

    protected function getTransitionActionColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::CONFIRMED,
            OrderStatus::DELIVERED,
            OrderStatus::SETTLED => 'success',

            OrderStatus::CANCELLED_BY_CUSTOMER,
            OrderStatus::CANCELLED_NO_ANSWER,
            OrderStatus::OUT_OF_STOCK,
            OrderStatus::REJECTED_QUALITY,
            OrderStatus::DELIVERY_FAILED => 'danger',

            OrderStatus::UNREACHABLE,
            OrderStatus::RETURNING,
            OrderStatus::RETURN_REQUESTED,
            OrderStatus::PENDING_CONFIRMATION => 'warning',

            OrderStatus::RESERVED,
            OrderStatus::PACKED => 'info',

            OrderStatus::SHIPPED,
            OrderStatus::OUT_FOR_DELIVERY => 'primary',

            default => 'gray',
        };
    }

    protected function getTransitionActionIcon(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::CONFIRMED => 'heroicon-o-check',
            OrderStatus::CANCELLED_BY_CUSTOMER => 'heroicon-o-x-mark',
            OrderStatus::UNREACHABLE => 'heroicon-o-phone-x-mark',
            OrderStatus::CANCELLED_NO_ANSWER => 'heroicon-o-x-circle',
            OrderStatus::RESERVED => 'heroicon-o-archive-box-arrow-down',
            OrderStatus::OUT_OF_STOCK => 'heroicon-o-exclamation-triangle',
            OrderStatus::PACKED => 'heroicon-o-cube',
            OrderStatus::SHIPPED => 'heroicon-o-paper-airplane',
            OrderStatus::OUT_FOR_DELIVERY => 'heroicon-o-truck',
            OrderStatus::DELIVERED => 'heroicon-o-check-circle',
            OrderStatus::DELIVERY_FAILED => 'heroicon-o-exclamation-circle',
            OrderStatus::RETURNING => 'heroicon-o-arrow-uturn-left',
            OrderStatus::RETURNED => 'heroicon-o-inbox-arrow-down',
            OrderStatus::SETTLED => 'heroicon-o-banknotes',
            default => 'heroicon-o-arrow-path',
        };
    }
}
