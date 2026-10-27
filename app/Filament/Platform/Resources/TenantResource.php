<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\TenantResource\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\ImpersonationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * @extends resource<Tenant>
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'المتاجر والمستأجرين';

    protected static ?string $modelLabel = 'متجر';

    protected static ?string $pluralModelLabel = 'المتاجر';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('id')
                ->label('معرف المتجر / النطاق الفرعي')
                ->required()
                ->unique(ignoreRecord: true),

            TextInput::make('name')
                ->label('اسم المتجر')
                ->required(),

            Select::make('status')
                ->label('الحالة')
                ->options([
                    'active' => 'نشط',
                    'provisioning' => 'قيد التجهيز',
                    'suspended' => 'معلق / موقوف',
                    'archived' => 'مؤرشف',
                ])
                ->default('active')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('المعرف / النطاق')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('اسم المتجر')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'provisioning',
                        'danger' => 'suspended',
                        'gray' => 'archived',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'provisioning' => 'قيد التجهيز',
                        'suspended' => 'موقوف',
                        'archived' => 'مؤرشف',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('impersonate')
                    ->label('دخول كتاجر')
                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد الدخول كتاجر')
                    ->modalDescription('سيتم تسجيل الدخول بصلاحيات مالك هذا المتجر لمدة 60 دقيقة وتوثيق العملية في سجل التدقيق.')
                    ->action(function (Tenant $record) {
                        /** @var User|null $admin */
                        $admin = Auth::user();
                        if (! $admin || ! $admin->isPlatformAdmin()) {
                            Notification::make()
                                ->title('غير مصرح لك بالدخول كتاجر')
                                ->danger()
                                ->send();

                            return null;
                        }

                        $service = app(ImpersonationService::class);
                        $service->impersonate($admin, $record);

                        Notification::make()
                            ->title('تم بدء جلسة الدخول كتاجر بنجاح')
                            ->success()
                            ->send();

                        return redirect("/store/{$record->id}");
                    }),

                Action::make('toggle_status')
                    ->label(fn (Tenant $record): string => $record->status === 'active' ? 'إيقاف المتجر' : 'تفعيل المتجر')
                    ->icon(fn (Tenant $record): string => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Tenant $record): string => $record->status === 'active' ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Tenant $record) {
                        $newStatus = $record->status === 'active' ? 'suspended' : 'active';
                        $record->update(['status' => $newStatus]);

                        /** @var User|null $admin */
                        $admin = Auth::user();

                        AuditLogger::log(
                            action: 'tenant.status_change',
                            subject: $record,
                            before: ['status' => $record->status === 'active' ? 'suspended' : 'active'],
                            after: ['status' => $newStatus],
                            actor: $admin,
                            tenantId: (string) $record->id
                        );

                        Notification::make()
                            ->title("تم تحديث حالة المتجر إلى [{$newStatus}]")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
        ];
    }
}
