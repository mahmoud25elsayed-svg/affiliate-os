<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\AbandonedCartResource\Pages\ListAbandonedCarts;
use App\Models\AbandonedCart;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @extends resource<AbandonedCart>
 */
class AbandonedCartResource extends Resource
{
    protected static ?string $model = AbandonedCart::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';

    public static function getNavigationLabel(): string
    {

        return __('السلات المتروكة');

    }

    public static function getModelLabel(): string
    {
        return __('سلة متروكة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('السلات المتروكة');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('التسويق والمبيعات');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_name')
                    ->label(__('العميل'))
                    ->formatStateUsing(function (AbandonedCart $record): string {
                        $name = $record->customer_name ?? __('زائر');
                        $contact = $record->customer_phone ?? $record->customer_email ?? '-';

                        return "{$name} ({$contact})";
                    })
                    ->searchable(['customer_name', 'customer_phone', 'customer_email'])
                    ->weight('bold'),

                TextColumn::make('item_count')
                    ->label(__('عدد المنتجات'))
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('subtotal_minor')
                    ->label(__('قيمة السلة'))
                    ->formatStateUsing(fn (int $state): string => Money::fromMinor($state)->format())
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('last_active_at')
                    ->label(__('آخر نشاط'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('حالة الاسترجاع'))
                    ->state(function (AbandonedCart $record): string {
                        if ($record->isRecovered()) {
                            return __('تم الاسترجاع بنجاح ✅');
                        }
                        if ($record->reminded_at !== null) {
                            return __('تم إرسال تذكير 🔔');
                        }

                        return __('متروكة ⏳');
                    })
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, __('تم الاسترجاع بنجاح ✅')) => 'success',
                        str_contains($state, __('تم إرسال تذكير 🔔')) => 'warning',
                        default => 'danger',
                    }),
            ])
            ->actions([
                Action::make('copy_link')
                    ->label(__('رابط الاسترجاع'))
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->action(function (AbandonedCart $record) {
                        $link = url('/cart/recover/'.$record->recovery_token);
                        Notification::make()
                            ->title(__('تم توليد رابط الاسترجاع'))
                            ->body($link)
                            ->info()
                            ->send();
                    }),
            ])
            ->defaultSort('last_active_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbandonedCarts::route('/'),
        ];
    }
}
