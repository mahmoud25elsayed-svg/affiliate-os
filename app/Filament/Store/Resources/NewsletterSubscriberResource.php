<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\NewsletterSubscriberResource\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @extends resource<NewsletterSubscriber>
 */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';

    public static function getNavigationLabel(): string
    {

        return __('المشتركون في النشرة');

    }

    public static function getModelLabel(): string
    {
        return __('مشترك في النشرة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المشتركون في النشرة البريدية');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('التسويق والمبيعات');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('بيانات المشترك'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('email')
                        ->label(__('البريد الإلكتروني'))
                        ->email()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('name')
                        ->label(__('الاسم (اختياري)'))
                        ->maxLength(100),

                    Select::make('source')
                        ->label(__('مصدر الاشتراك'))
                        ->options([
                            'storefront_footer' => __('تذييل المتجر'),
                            'popup' => __('نافذة منبثقة'),
                            'checkout' => __('أثناء الدفع'),
                        ])
                        ->default('storefront_footer'),

                    Toggle::make('is_active')
                        ->label(__('نشط ومفعل'))
                        ->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label(__('البريد الإلكتروني'))
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->searchable()
                    ->default('-'),

                TextColumn::make('source')
                    ->label(__('المصدر'))
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label(__('الحالة'))
                    ->boolean(),

                TextColumn::make('subscribed_at')
                    ->label(__('تاريخ الاشتراك'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('subscribed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscribers::route('/'),
        ];
    }
}
