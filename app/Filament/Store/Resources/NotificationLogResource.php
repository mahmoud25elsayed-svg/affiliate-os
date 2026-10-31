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

    protected static ?string $navigationLabel = 'سجل الإرسال';

    protected static ?string $modelLabel = 'سجل إرسال';

    protected static ?string $pluralModelLabel = 'سجلات الإرسال';

    protected static \UnitEnum|string|null $navigationGroup = 'الإشعارات والرسائل';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipient')
                    ->label('المستقبل')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('channel')
                    ->label('القناة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sms' => 'primary',
                        'whatsapp' => 'success',
                        'email' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('event_type')
                    ->label('الحدث')
                    ->badge(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent', 'delivered' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'تم الإرسال',
                        'delivered' => 'تم التسليم',
                        'failed' => 'فشل',
                        'pending' => 'قيد الإرسال',
                        default => $state,
                    }),

                TextColumn::make('cost_minor')
                    ->label('التكلفة')
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state)->format()),

                TextColumn::make('error_message')
                    ->label('ملاحظات الخطأ')
                    ->limit(30)
                    ->tooltip(fn (NotificationLog $record): ?string => $record->error_message),

                TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label('القناة')
                    ->options([
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        'in_app' => 'In-App',
                    ]),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'sent' => 'تم الإرسال',
                        'failed' => 'فشل',
                        'pending' => 'قيد الإرسال',
                    ]),
            ])
            ->actions([
                Action::make('retry')
                    ->label('إعادة الإرسال')
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
                            ->title('تمت إعادة إرسال الإشعار')
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
