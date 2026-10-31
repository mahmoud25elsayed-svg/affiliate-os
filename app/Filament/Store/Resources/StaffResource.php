<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Filament\Store\Resources\StaffResource\Pages\ListStaff;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * @extends resource<User>
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    public static function getNavigationLabel(): string
    {

        return __('فريق العمل والصلاحيات');

    }

    public static function getModelLabel(): string
    {
        return __('موظف');
    }

    public static function getPluralModelLabel(): string
    {
        return __('الموظفون');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('اسم الموظف'))
                ->required(),

            TextInput::make('email')
                ->label(__('البريد الإلكتروني'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            TextInput::make('phone')
                ->label(__('رقم الهاتف')),

            TextInput::make('password')
                ->label(__('كلمة المرور'))
                ->password()
                ->dehydrateStateUsing(fn ($state) => ! empty($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),

            Select::make('role')
                ->label(__('الدور الوظيفي'))
                ->options([
                    TenantPermissionMatrix::ROLE_OWNER => __('مالك المتجر (Owner)'),
                    TenantPermissionMatrix::ROLE_MANAGER => __('مدير المتجر (Manager)'),
                    TenantPermissionMatrix::ROLE_CATALOG => __('مسؤول الكتالوج (Catalog)'),
                    TenantPermissionMatrix::ROLE_ORDERS => __('مسؤول الطلبات (Orders)'),
                    TenantPermissionMatrix::ROLE_FINANCE => __('مسؤول المالية (Finance)'),
                    TenantPermissionMatrix::ROLE_SUPPORT => __('خدمة العملاء (Support)'),
                    TenantPermissionMatrix::ROLE_CALL_AGENT => __('كول سنتر (Call Agent)'),
                ])
                ->default(TenantPermissionMatrix::ROLE_MANAGER)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('الاسم'))
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('البريد'))
                    ->searchable(),

                TextColumn::make('phone')
                    ->label(__('الهاتف')),

                TextColumn::make('roles.name')
                    ->label(__('الدور'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner' => __('مالك المتجر'),
                        'manager' => __('مدير'),
                        'catalog' => __('كتالوج'),
                        'orders' => __('طلبات'),
                        'finance' => __('مالية'),
                        'support' => __('دعم فني'),
                        'call_agent' => __('كول سنتر'),
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الإضافة'))
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery();

        if (tenancy()->initialized) {
            $query->where('tenant_id', tenant('id'));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
        ];
    }
}
