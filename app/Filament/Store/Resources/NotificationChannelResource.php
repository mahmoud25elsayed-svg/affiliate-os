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

    public static function getNavigationLabel(): string
    {

        return __('قنوات الإرسال');

    }

    public static function getModelLabel(): string
    {
        return __('قناة إرسال');
    }

    public static function getPluralModelLabel(): string
    {
        return __('قنوات الإرسال');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('الإشعارات والرسائل');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('معلومات القناة والمزود'))
                    ->columns(2)
                    ->schema([
                        Select::make('channel')
                            ->label(__('القناة'))
                            ->options([
                                ChannelEnum::SMS->value => __('رسائل قصيرة (SMS)'),
                                ChannelEnum::WHATSAPP->value => __('واتساب (WhatsApp)'),
                                ChannelEnum::EMAIL->value => __('بريد إلكتروني (Email)'),
                                ChannelEnum::IN_APP->value => __('إشعار داخلي (In-App)'),
                            ])
                            ->required(),

                        Select::make('provider')
                            ->label(__('مزود الخدمة'))
                            ->options([
                                'sms_misr' => __('SMS Misr (مصر)'),
                                'meta_whatsapp' => 'Meta WhatsApp Cloud API',
                                'system_mail' => __('البريد الإلكتروني المباشر (SMTP)'),
                                'in_app' => __('قاعدة البيانات (In-App)'),
                                'fake' => __('مزود تجريبي (Fake Provider)'),
                            ])
                            ->required(),

                        TextInput::make('sender_id')
                            ->label(__('اسم المرسل (Sender ID)'))
                            ->placeholder('AffiliateOS')
                            ->maxLength(64),

                        TextInput::make('daily_cost_limit_minor')
                            ->label(__('الحد اليومي للإنفاق (بالقروش)'))
                            ->numeric()
                            ->default(50000)
                            ->helperText(__('50000 = 500 بعملة المتجر يومياً')),

                        Toggle::make('is_active')
                            ->label(__('مفعلة'))
                            ->default(true),

                        Toggle::make('is_default')
                            ->label(__('القناة الافتراضية للنوع'))
                            ->default(false),
                    ]),

                Section::make(__('بيانات الاتصال ومفاتيح API'))
                    ->description(__('تُخزن هذه البيانات مشفرة بالكامل في قاعدة البيانات'))
                    ->schema([
                        KeyValue::make('credentials')
                            ->label(__('مفاتيح التكوين (Credentials)'))
                            ->keyLabel(__('المفتاح (Key: username, password, access_token, phone_number_id)'))
                            ->valueLabel(__('القيمة (Value)')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('channel')
                    ->label(__('القناة'))
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
                    ->label(__('المزود'))
                    ->weight('bold'),

                TextColumn::make('sender_id')
                    ->label(__('اسم المرسل'))
                    ->default('-'),

                TextColumn::make('daily_cost_limit_minor')
                    ->label(__('الحد اليومي'))
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state)->format()),

                TextColumn::make('current_day_cost_minor')
                    ->label(__('استهلاك اليوم'))
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state)->format()),

                IconColumn::make('is_active')
                    ->label(__('مفعلة'))
                    ->boolean(),

                IconColumn::make('is_default')
                    ->label(__('افتراضية'))
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
