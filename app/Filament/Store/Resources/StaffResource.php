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

    protected static ?string $navigationLabel = 'فريق العمل والصلاحيات';

    protected static ?string $modelLabel = 'موظف';

    protected static ?string $pluralModelLabel = 'الموظفون';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('اسم الموظف')
                ->required(),

            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            TextInput::make('phone')
                ->label('رقم الهاتف'),

            TextInput::make('password')
                ->label('كلمة المرور')
                ->password()
                ->dehydrateStateUsing(fn ($state) => ! empty($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create'),

            Select::make('role')
                ->label('الدور الوظيفي')
                ->options([
                    TenantPermissionMatrix::ROLE_OWNER => 'مالك المتجر (Owner)',
                    TenantPermissionMatrix::ROLE_MANAGER => 'مدير المتجر (Manager)',
                    TenantPermissionMatrix::ROLE_CATALOG => 'مسؤول الكتالوج (Catalog)',
                    TenantPermissionMatrix::ROLE_ORDERS => 'مسؤول الطلبات (Orders)',
                    TenantPermissionMatrix::ROLE_FINANCE => 'مسؤول المالية (Finance)',
                    TenantPermissionMatrix::ROLE_SUPPORT => 'خدمة العملاء (Support)',
                    TenantPermissionMatrix::ROLE_CALL_AGENT => 'كول سنتر (Call Agent)',
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
                    ->label('الاسم')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('الهاتف'),

                TextColumn::make('roles.name')
                    ->label('الدور')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'owner' => 'مالك المتجر',
                        'manager' => 'مدير',
                        'catalog' => 'كتالوج',
                        'orders' => 'طلبات',
                        'finance' => 'مالية',
                        'support' => 'دعم فني',
                        'call_agent' => 'كول سنتر',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
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
