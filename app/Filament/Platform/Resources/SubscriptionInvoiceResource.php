<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\SubscriptionInvoiceResource\Pages\ListSubscriptionInvoices;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Support\Billing\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * @extends resource<SubscriptionInvoice>
 */
class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-receipt-percent';

    public static function getNavigationLabel(): string
    {

        return __('فواتير الاشتراكات');

    }

    public static function getModelLabel(): string
    {
        return __('فاتورة اشتراك');
    }

    public static function getPluralModelLabel(): string
    {
        return __('فواتير الاشتراكات');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('الفوترة والاشتراكات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('رقم الفاتورة'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                TextColumn::make('tenant.name')
                    ->label(__('المتجر'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subscription.plan.name_ar')
                    ->label(__('الباقة'))
                    ->badge(),

                TextColumn::make('amount')
                    ->label(__('المبلغ'))
                    ->formatStateUsing(fn (SubscriptionInvoice $record) => $record->amountMoney()->format())
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->label(__('وسيلة الدفع'))
                    ->badge(),

                TextColumn::make('payment_reference')
                    ->label(__('الرقم المرجعي'))
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => __('قيد المراجعة'),
                        'paid' => __('مدفوعة ومفعلة'),
                        'rejected' => __('مرفوضة'),
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الإنشاء'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view_receipt')
                    ->label(__('معاينة الإيصال'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (SubscriptionInvoice $record) => ! empty($record->receipt_path))
                    ->url(fn (SubscriptionInvoice $record) => asset('storage/'.$record->receipt_path), shouldOpenInNewTab: true),

                Action::make('download_pdf')
                    ->label(__('تحميل PDF'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn (SubscriptionInvoice $record) => route('billing.invoices.download', $record), shouldOpenInNewTab: true),

                Action::make('approve_payment')
                    ->label(__('اعتماد السداد'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('اعتماد سداد الفاتورة وتفعيل الاشتراك'))
                    ->modalDescription(__('هل تأكدت من وصول المبلغ إلى الحساب البنكي / المحفظة؟ سيتم تفعيل المتجر والاشتراك فورًا.'))
                    ->visible(fn (SubscriptionInvoice $record) => $record->isPending())
                    ->action(function (SubscriptionInvoice $record, SubscriptionService $service) {
                        /** @var User $admin */
                        $admin = Auth::user();
                        $service->approveInvoice($record, $admin);

                        Notification::make()
                            ->title(__('تم اعتماد السداد وتفعيل الاشتراك بنجاح'))
                            ->success()
                            ->send();
                    }),

                Action::make('reject_payment')
                    ->label(__('رفض السداد'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SubscriptionInvoice $record) => $record->isPending())
                    ->schema([
                        Textarea::make('reason')
                            ->label(__('سبب الرفض'))
                            ->required()
                            ->placeholder(__('مثال: لم يتم العثور على العملية في كشف الحساب')),
                    ])
                    ->action(function (SubscriptionInvoice $record, array $data, SubscriptionService $service) {
                        /** @var User $admin */
                        $admin = Auth::user();
                        $service->rejectInvoice($record, $admin, (string) $data['reason']);

                        Notification::make()
                            ->title(__('تم رفض الفاتورة وإشعار التاجر'))
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionInvoices::route('/'),
        ];
    }
}
