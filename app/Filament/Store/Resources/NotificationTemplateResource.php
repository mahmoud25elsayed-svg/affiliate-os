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

    public static function getNavigationLabel(): string
    {

        return __('قوالب الرسائل');

    }

    public static function getModelLabel(): string
    {
        return __('قالب رسالة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('قوالب الرسائل');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('الإشعارات والرسائل');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('إعدادات القالب'))
                    ->columns(3)
                    ->schema([
                        Select::make('event_type')
                            ->label(__('الحدث المرتبط'))
                            ->options([
                                EventTypeEnum::ORDER_CONFIRMED->value => __('تأكيد الطلب'),
                                EventTypeEnum::ORDER_SHIPPED->value => __('شحن الطلب'),
                                EventTypeEnum::ORDER_DELIVERED->value => __('تسليم الطلب'),
                                EventTypeEnum::ORDER_CANCELLED->value => __('إلغاء الطلب'),
                                EventTypeEnum::CART_ABANDONED->value => __('سلة متروكة'),
                                EventTypeEnum::OTP_VERIFICATION->value => __('رمز التحقق (OTP)'),
                                EventTypeEnum::WELCOME->value => __('ترحيب / اشتراك'),
                            ])
                            ->required(),

                        Select::make('channel')
                            ->label(__('القناة'))
                            ->options([
                                ChannelEnum::SMS->value => __('رسائل قصيرة (SMS)'),
                                ChannelEnum::WHATSAPP->value => __('واتساب (WhatsApp)'),
                                ChannelEnum::EMAIL->value => __('بريد إلكتروني (Email)'),
                                ChannelEnum::IN_APP->value => __('إشعار داخلي (In-App)'),
                            ])
                            ->required(),

                        Select::make('locale')
                            ->label(__('اللغة'))
                            ->options([
                                'ar' => __('العربية (Arabic)'),
                                'en' => __('الإنجليزية (English)'),
                            ])
                            ->default('ar')
                            ->required(),
                    ]),

                Section::make(__('محتوى الرسالة'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('عنوان القالب التعريفي'))
                            ->placeholder(__('مثال: رسالة شحن الطلب السريعة')),

                        TextInput::make('subject')
                            ->label(__('عنوان الموضوع (للبريد الإلكتروني والإشعارات الداخلية)'))
                            ->placeholder(__('تم شحن طلبك #{order_number}')),

                        Textarea::make('content')
                            ->label(__('نص الرسالة (المتغيرات المتاحة)'))
                            ->required()
                            ->rows(5)
                            ->helperText(__('المتغيرات المتاحة للاستبدال: {customer_name}, {order_number}, {order_total}, {store_name}, {courier_name}, {tracking_number}, {tracking_url}, {recovery_url}, {otp_code}')),

                        Toggle::make('is_active')
                            ->label(__('مفعل'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')
                    ->label(__('الحدث'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'order_confirmed' => __('تأكيد طلب'),
                        'order_shipped' => __('شحن طلب'),
                        'order_delivered' => __('تسليم طلب'),
                        'order_cancelled' => __('إلغاء طلب'),
                        'cart_abandoned' => __('سلة متروكة'),
                        'otp_verification' => 'OTP',
                        'welcome' => __('ترحيب'),
                        default => $state,
                    })
                    ->color('primary'),

                TextColumn::make('channel')
                    ->label(__('القناة'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('locale')
                    ->label(__('اللغة'))
                    ->badge(),

                TextColumn::make('title')
                    ->label(__('العنوان'))
                    ->default('-')
                    ->weight('bold'),

                TextColumn::make('content')
                    ->label(__('مقتطف النص'))
                    ->limit(50),

                IconColumn::make('is_active')
                    ->label(__('مفعل'))
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
