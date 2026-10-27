<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Notifications\Enums\NotificationChannel as ChannelEnum;
use App\Domain\Notifications\Enums\NotificationEventType as EventTypeEnum;
use App\Filament\Store\Resources\NotificationTemplateResource\Pages\CreateNotificationTemplate;
use App\Filament\Store\Resources\NotificationTemplateResource\Pages\EditNotificationTemplate;
use App\Filament\Store\Resources\NotificationTemplateResource\Pages\ListNotificationTemplates;
use App\Models\NotificationTemplate;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @extends resource<NotificationTemplate>
 */
class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'قوالب الرسائل';

    protected static ?string $modelLabel = 'قالب رسالة';

    protected static ?string $pluralModelLabel = 'قوالب الرسائل';

    protected static \UnitEnum|string|null $navigationGroup = 'الإشعارات والرسائل';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('إعدادات القالب')
                    ->columns(3)
                    ->schema([
                        Select::make('event_type')
                            ->label('الحدث المرتبط')
                            ->options([
                                EventTypeEnum::ORDER_CONFIRMED->value => 'تأكيد الطلب',
                                EventTypeEnum::ORDER_SHIPPED->value => 'شحن الطلب',
                                EventTypeEnum::ORDER_DELIVERED->value => 'تسليم الطلب',
                                EventTypeEnum::ORDER_CANCELLED->value => 'إلغاء الطلب',
                                EventTypeEnum::CART_ABANDONED->value => 'سلة متروكة',
                                EventTypeEnum::OTP_VERIFICATION->value => 'رمز التحقق (OTP)',
                                EventTypeEnum::WELCOME->value => 'ترحيب / اشتراك',
                            ])
                            ->required(),

                        Select::make('channel')
                            ->label('القناة')
                            ->options([
                                ChannelEnum::SMS->value => 'رسائل قصيرة (SMS)',
                                ChannelEnum::WHATSAPP->value => 'واتساب (WhatsApp)',
                                ChannelEnum::EMAIL->value => 'بريد إلكتروني (Email)',
                                ChannelEnum::IN_APP->value => 'إشعار داخلي (In-App)',
                            ])
                            ->required(),

                        Select::make('locale')
                            ->label('اللغة')
                            ->options([
                                'ar' => 'العربية (Arabic)',
                                'en' => 'الإنجليزية (English)',
                            ])
                            ->default('ar')
                            ->required(),
                    ]),

                Section::make('محتوى الرسالة')
                    ->schema([
                        TextInput::make('title')
                            ->label('عنوان القالب التعريفي')
                            ->placeholder('مثال: رسالة شحن الطلب السريعة'),

                        TextInput::make('subject')
                            ->label('عنوان الموضوع (للبريد الإلكتروني والإشعارات الداخلية)')
                            ->placeholder('تم شحن طلبك #{order_number}'),

                        Textarea::make('content')
                            ->label('نص الرسالة (المتغيرات المتاحة)')
                            ->required()
                            ->rows(5)
                            ->helperText('المتغيرات المتاحة للاستبدال: {customer_name}, {order_number}, {order_total}, {store_name}, {courier_name}, {tracking_number}, {tracking_url}, {recovery_url}, {otp_code}'),

                        Toggle::make('is_active')
                            ->label('مفعل')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')
                    ->label('الحدث')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'order_confirmed' => 'تأكيد طلب',
                        'order_shipped' => 'شحن طلب',
                        'order_delivered' => 'تسليم طلب',
                        'order_cancelled' => 'إلغاء طلب',
                        'cart_abandoned' => 'سلة متروكة',
                        'otp_verification' => 'OTP',
                        'welcome' => 'ترحيب',
                        default => $state,
                    })
                    ->color('primary'),

                TextColumn::make('channel')
                    ->label('القناة')
                    ->badge()
                    ->color('info'),

                TextColumn::make('locale')
                    ->label('اللغة')
                    ->badge(),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->default('-')
                    ->weight('bold'),

                TextColumn::make('content')
                    ->label('مقتطف النص')
                    ->limit(50),

                IconColumn::make('is_active')
                    ->label('مفعل')
                    ->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationTemplates::route('/'),
            'create' => CreateNotificationTemplate::route('/create'),
            'edit' => EditNotificationTemplate::route('/{record}/edit'),
        ];
    }
}
