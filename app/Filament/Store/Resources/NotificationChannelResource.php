<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Notifications\Enums\NotificationChannel as ChannelEnum;
use App\Filament\Store\Resources\NotificationChannelResource\Pages\CreateNotificationChannel;
use App\Filament\Store\Resources\NotificationChannelResource\Pages\EditNotificationChannel;
use App\Filament\Store\Resources\NotificationChannelResource\Pages\ListNotificationChannels;
use App\Models\NotificationChannel;
use App\Support\Money;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @extends resource<NotificationChannel>
 */
class NotificationChannelResource extends Resource
{
    protected static ?string $model = NotificationChannel::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'قنوات الإرسال';

    protected static ?string $modelLabel = 'قناة إرسال';

    protected static ?string $pluralModelLabel = 'قنوات الإرسال';

    protected static \UnitEnum|string|null $navigationGroup = 'الإشعارات والرسائل';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات القناة والمزود')
                    ->columns(2)
                    ->schema([
                        Select::make('channel')
                            ->label('القناة')
                            ->options([
                                ChannelEnum::SMS->value => 'رسائل قصيرة (SMS)',
                                ChannelEnum::WHATSAPP->value => 'واتساب (WhatsApp)',
                                ChannelEnum::EMAIL->value => 'بريد إلكتروني (Email)',
                                ChannelEnum::IN_APP->value => 'إشعار داخلي (In-App)',
                            ])
                            ->required(),

                        Select::make('provider')
                            ->label('مزود الخدمة')
                            ->options([
                                'sms_misr' => 'SMS Misr (مصر)',
                                'meta_whatsapp' => 'Meta WhatsApp Cloud API',
                                'system_mail' => 'البريد الإلكتروني المباشر (SMTP)',
                                'in_app' => 'قاعدة البيانات (In-App)',
                                'fake' => 'مزود تجريبي (Fake Provider)',
                            ])
                            ->required(),

                        TextInput::make('sender_id')
                            ->label('اسم المرسل (Sender ID)')
                            ->placeholder('AffiliateOS')
                            ->maxLength(64),

                        TextInput::make('daily_cost_limit_minor')
                            ->label('الحد اليومي للإنفاق (بالقروش)')
                            ->numeric()
                            ->default(50000)
                            ->helperText('50000 قرش = 500 جنيه مصري يومياً'),

                        Toggle::make('is_active')
                            ->label('مفعلة')
                            ->default(true),

                        Toggle::make('is_default')
                            ->label('القناة الافتراضية للنوع')
                            ->default(false),
                    ]),

                Section::make('بيانات الاتصال ومفاتيح API')
                    ->description('تُخزن هذه البيانات مشفرة بالكامل في قاعدة البيانات')
                    ->schema([
                        KeyValue::make('credentials')
                            ->label('مفاتيح التكوين (Credentials)')
                            ->keyLabel('المفتاح (Key: username, password, access_token, phone_number_id)')
                            ->valueLabel('القيمة (Value)'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel')
                    ->label('القناة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        'in_app' => 'In-App',
                        default => $state,
                    })
                    ->color('primary'),

                TextColumn::make('provider')
                    ->label('المزود')
                    ->weight('bold'),

                TextColumn::make('sender_id')
                    ->label('اسم المرسل')
                    ->default('-'),

                TextColumn::make('daily_cost_limit_minor')
                    ->label('الحد اليومي')
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state)->format()),

                TextColumn::make('current_day_cost_minor')
                    ->label('استهلاك اليوم')
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state)->format()),

                IconColumn::make('is_active')
                    ->label('مفعلة')
                    ->boolean(),

                IconColumn::make('is_default')
                    ->label('افتراضية')
                    ->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationChannels::route('/'),
            'create' => CreateNotificationChannel::route('/create'),
            'edit' => EditNotificationChannel::route('/{record}/edit'),
        ];
    }
}
