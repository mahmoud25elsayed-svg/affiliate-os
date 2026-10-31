<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Notifications\Enums\NotificationChannel as ChannelEnum;
use App\Domain\Notifications\Services\NotificationManager;
use App\Filament\Store\Resources\NotificationLogResource\Pages\ListNotificationLogs;
use App\Models\NotificationLog;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * @extends resource<NotificationLog>
 */
class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';

    public static function getNavigationLabel(): string
    {

        return __('سجل الإرسال');

    }

    public static function getModelLabel(): string
    {
        return __('سجل إرسال');
    }

    public static function getPluralModelLabel(): string
    {
        return __('سجلات الإرسال');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('الإشعارات والرسائل');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipient')
                    ->label(__('المستقبل'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('channel')
                    ->label(__('القناة'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sms' => 'primary',
                        'whatsapp' => 'success',
                        'email' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('event_type')
                    ->label(__('الحدث'))
                    ->badge(),

                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent', 'delivered' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => __('تم الإرسال'),
                        'delivered' => __('تم التسليم'),
                        'failed' => __('فشل'),
                        'pending' => __('قيد الإرسال'),
                        default => $state,
                    }),

                TextColumn::make('cost_minor')
                    ->label(__('التكلفة'))
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state)->format()),

                TextColumn::make('error_message')
                    ->label(__('ملاحظات الخطأ'))
                    ->limit(30)
                    ->tooltip(fn (NotificationLog $record): ?string => $record->error_message),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الإرسال'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label(__('القناة'))
                    ->options([
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        'in_app' => 'In-App',
                    ]),

                SelectFilter::make('status')
                    ->label(__('الحالة'))
                    ->options([
                        'sent' => __('تم الإرسال'),
                        'failed' => __('فشل'),
                        'pending' => __('قيد الإرسال'),
                    ]),
            ])
            ->actions([
                Action::make('retry')
                    ->label(__('إعادة الإرسال'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (NotificationLog $record): bool => $record->status === 'failed')
                    ->action(function (NotificationLog $record, NotificationManager $manager): void {
                        $manager->send(
                            channel: ChannelEnum::from($record->channel),
                            recipient: $record->recipient,
                            eventType: $record->event_type,
                            data: ['content' => $record->content]
                        );

                        Notification::make()
                            ->title(__('تمت إعادة إرسال الإشعار'))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationLogs::route('/'),
        ];
    }
}
