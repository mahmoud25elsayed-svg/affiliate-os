<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\ReviewResource\Pages\ListReviews;
use App\Models\Customer;
use App\Models\ProductReview;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Lunar\Models\Product;

/**
 * @extends resource<ProductReview>
 */
class ReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-star';

    public static function getNavigationLabel(): string
    {

        return __('المراجعات والتقييمات');

    }

    public static function getModelLabel(): string
    {
        return __('مراجعة');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المراجعات والتقييمات');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('المتجر والعملاء');
    }

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.id')
                    ->label(__('المنتج'))
                    ->formatStateUsing(function (ProductReview $record) {
                        $product = $record->product;
                        if ($product instanceof Product) {
                            return $product->translateAttribute('name') ?? __('منتج #').$product->id;
                        }

                        return '-';
                    })
                    ->searchable(),

                TextColumn::make('customer.id')
                    ->label(__('العميل'))
                    ->formatStateUsing(function (ProductReview $record) {
                        $customer = $record->customer;

                        return $customer instanceof Customer ? $customer->full_name : __('عميل');
                    })
                    ->description(function (ProductReview $record) {
                        $customer = $record->customer;

                        return ($customer instanceof Customer && $customer->phone_e164) ? $customer->phone_e164 : '-';
                    })
                    ->searchable(),

                TextColumn::make('rating')
                    ->label(__('التقييم'))
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        5, 4 => 'success',
                        3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => "{$state} ★"),

                TextColumn::make('comment')
                    ->label(__('نص المراجعة'))
                    ->limit(50)
                    ->tooltip(fn (ProductReview $record): string => $record->comment),

                IconColumn::make('is_verified_purchase')
                    ->label(__('مشتري مؤكد'))
                    ->boolean(),

                TextColumn::make('status')
                    ->label(__('الحالة'))
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => __('قيد المراجعة'),
                        'approved' => __('معتمدة'),
                        'rejected' => __('مرفوضة'),
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الإرسال'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('حالة المراجعة'))
                    ->options([
                        'pending' => __('قيد المراجعة'),
                        'approved' => __('معتمدة'),
                        'rejected' => __('مرفوضة'),
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label(__('اعتماد'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ProductReview $record) => $record->status !== 'approved')
                    ->action(function (ProductReview $record) {
                        $record->update(['status' => 'approved', 'rejection_reason' => null]);
                        Notification::make()->title(__('تم اعتماد المراجعة بنجاح'))->success()->send();
                    }),

                Action::make('reject')
                    ->label(__('رفض'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ProductReview $record) => $record->status !== 'rejected')
                    ->form([
                        Textarea::make('reason')
                            ->label(__('سبب الرفض'))
                            ->required(),
                    ])
                    ->action(function (ProductReview $record, array $data) {
                        $record->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);
                        Notification::make()->title(__('تم رفض المراجعة'))->warning()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
