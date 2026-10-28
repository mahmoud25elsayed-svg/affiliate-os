<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources;

use App\Domain\Affiliate\Services\AffiliateKycService;
use App\Filament\Store\Resources\AffiliateResource\Pages\EditAffiliate;
use App\Filament\Store\Resources\AffiliateResource\Pages\ListAffiliates;
use App\Models\Affiliate;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * @extends resource<Affiliate>
 */
class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'المسوقون (الأفلييت)';

    protected static ?string $modelLabel = 'مسوق';

    protected static ?string $pluralModelLabel = 'المسوقون والأفلييت';

    protected static \UnitEnum|string|null $navigationGroup = 'برنامج الأفلييت';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('البيانات الأساسية')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('الاسم بالكامل')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('رقم الهاتف (المصري)')
                        ->disabled()
                        ->required(),

                    TextInput::make('email')
                        ->label('البريد الإلكتروني')
                        ->email()
                        ->nullable(),

                    TextInput::make('code')
                        ->label('كود الإحالة الفريد')
                        ->disabled(),
                ]),
            ]),

            Section::make('حالة الحساب والعمولة')->schema([
                Grid::make(2)->schema([
                    Select::make('status')
                        ->label('حالة الحساب')
                        ->options([
                            Affiliate::STATUS_PENDING => 'قيد الانتظار والمراجعة',
                            Affiliate::STATUS_ACTIVE => 'نشط ومفعل',
                            Affiliate::STATUS_SUSPENDED => 'معلق / موقوف مؤقتاً',
                            Affiliate::STATUS_REJECTED => 'مرفوض',
                        ])
                        ->required(),

                    TextInput::make('commission_rate')
                        ->label('نسبة العمولة الافتراضية (%)')
                        ->numeric()
                        ->default(10.00),

                    Textarea::make('suspension_reason')
                        ->label('سبب التعليق / الإيقاف')
                        ->rows(2)
                        ->columnSpanFull()
                        ->visible(fn ($get) => $get('status') === Affiliate::STATUS_SUSPENDED),
                ]),
            ]),

            Section::make('توثيق الهوية (KYC)')->schema([
                Grid::make(2)->schema([
                    Select::make('kyc_status')
                        ->label('حالة التوثيق')
                        ->options([
                            Affiliate::KYC_PENDING => 'قيد المراجعة',
                            Affiliate::KYC_APPROVED => 'تم التوثيق والاعتماد',
                            Affiliate::KYC_REJECTED => 'مرفوض',
                        ])
                        ->disabled(),

                    TextInput::make('national_id_number')
                        ->label('الرقم القومي (14 رقم)')
                        ->disabled(),

                    Textarea::make('kyc_rejected_reason')
                        ->label('سبب رفض التوثيق')
                        ->disabled()
                        ->rows(2)
                        ->columnSpanFull()
                        ->visible(fn ($get) => $get('kyc_status') === Affiliate::KYC_REJECTED),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم المسوق')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label('رقم الهاتف')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('code')
                    ->label('كود الإحالة')
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('commission_rate')
                    ->label('نسبة العمولة')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}%" : 'افتراضي')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('حالة الحساب')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Affiliate::STATUS_ACTIVE => 'نشط',
                        Affiliate::STATUS_PENDING => 'قيد الانتظار',
                        Affiliate::STATUS_SUSPENDED => 'معلق',
                        Affiliate::STATUS_REJECTED => 'مرفوض',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        Affiliate::STATUS_ACTIVE => 'success',
                        Affiliate::STATUS_PENDING => 'warning',
                        Affiliate::STATUS_SUSPENDED => 'danger',
                        Affiliate::STATUS_REJECTED => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('kyc_status')
                    ->label('توثيق KYC')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Affiliate::KYC_APPROVED => 'موثق',
                        Affiliate::KYC_PENDING => 'قيد الفحص',
                        Affiliate::KYC_REJECTED => 'مرفوض',
                        default => 'غير مقدم',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        Affiliate::KYC_APPROVED => 'success',
                        Affiliate::KYC_PENDING => 'warning',
                        Affiliate::KYC_REJECTED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('payout_security')
                    ->label('أمان السحب')
                    ->state(function (Affiliate $record): string {
                        if ($record->payouts_frozen_until && $record->payouts_frozen_until->isFuture()) {
                            return 'سحب مجمد حتى '.$record->payouts_frozen_until->format('m/d H:i');
                        }

                        return 'نشط';
                    })
                    ->badge()
                    ->color(fn (string $state) => str_contains($state, 'مجمد') ? 'danger' : 'success'),

                TextColumn::make('created_at')
                    ->label('تاريخ الانضمام')
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('تصفية حسب الحالة')
                    ->options([
                        Affiliate::STATUS_ACTIVE => 'نشط',
                        Affiliate::STATUS_PENDING => 'قيد الانتظار',
                        Affiliate::STATUS_SUSPENDED => 'معلق',
                        Affiliate::STATUS_REJECTED => 'مرفوض',
                    ]),

                SelectFilter::make('kyc_status')
                    ->label('حالة التوثيق')
                    ->options([
                        Affiliate::KYC_APPROVED => 'موثق',
                        Affiliate::KYC_PENDING => 'قيد الفحص',
                        Affiliate::KYC_REJECTED => 'مرفوض',
                    ]),
            ])
            ->actions([
                Action::make('approve_account')
                    ->label('تفعيل المسوق')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Affiliate $record) => $record->status === Affiliate::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (Affiliate $record): void {
                        $record->update(['status' => Affiliate::STATUS_ACTIVE]);
                        Notification::make()->title('تم تفعيل حساب المسوق بنجاح')->success()->send();
                    }),

                Action::make('suspend_account')
                    ->label('تعليق الحساب')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Affiliate $record) => $record->status === Affiliate::STATUS_ACTIVE)
                    ->form([
                        Textarea::make('reason')
                            ->label('سبب تعليق الحساب')
                            ->required(),
                    ])
                    ->action(function (Affiliate $record, array $data): void {
                        $record->update([
                            'status' => Affiliate::STATUS_SUSPENDED,
                            'suspension_reason' => $data['reason'],
                        ]);
                        Notification::make()->title('تم تعليق حساب المسوق')->warning()->send();
                    }),

                Action::make('review_kyc')
                    ->label('فحص وثائق KYC')
                    ->icon('heroicon-o-identification')
                    ->color('primary')
                    ->visible(fn (Affiliate $record) => ! empty($record->national_id_number))
                    ->modalHeading('فحص وتدقيق وثائق الهوية الوطنية')
                    ->modalDescription('تنبيه: يتم تسجيل هذا الإجراء في سجل التدقيق KYC Audit Trail تلقائياً.')
                    ->form(function (Affiliate $record) {
                        $frontUrl = route('store.affiliates.kyc-document', ['affiliate' => $record->id, 'side' => 'front']);
                        $backUrl = route('store.affiliates.kyc-document', ['affiliate' => $record->id, 'side' => 'back']);

                        return [
                            Placeholder::make('kyc_details')
                                ->label('بيانات المسوق')
                                ->content(new HtmlString("
                                    <div class='space-y-3 text-sm'>
                                        <div><strong>الاسم:</strong> {$record->name}</div>
                                        <div><strong>رقم الهاتف:</strong> {$record->phone}</div>
                                        <div><strong>الرقم القومي:</strong> <span class='font-mono font-bold text-primary-600'>{$record->national_id_number}</span></div>
                                        <div class='grid grid-cols-2 gap-4 mt-4'>
                                            <div class='border rounded p-3 text-center'>
                                                <p class='font-bold mb-2'>وجه البطاقة الأمامي</p>
                                                <a href='{$frontUrl}' target='_blank' class='inline-block bg-primary-50 text-primary-600 px-3 py-1 rounded text-xs font-semibold hover:bg-primary-100'>فتح المستند الأمامي بأمان &larr;</a>
                                            </div>
                                            <div class='border rounded p-3 text-center'>
                                                <p class='font-bold mb-2'>ظهر البطاقة الخلفي</p>
                                                <a href='{$backUrl}' target='_blank' class='inline-block bg-primary-50 text-primary-600 px-3 py-1 rounded text-xs font-semibold hover:bg-primary-100'>فتح المستند الخلفي بأمان &larr;</a>
                                            </div>
                                        </div>
                                    </div>
                                ")),

                            Select::make('decision')
                                ->label('قرار المراجعة')
                                ->options([
                                    'approve' => 'اعتماد وتوثيق الهوية (Approved)',
                                    'reject' => 'رفض التوثيق (Rejected)',
                                ])
                                ->required(),

                            Textarea::make('reject_reason')
                                ->label('سبب الرفض (في حالة الرفض)')
                                ->visible(fn ($get) => $get('decision') === 'reject')
                                ->required(fn ($get) => $get('decision') === 'reject'),
                        ];
                    })
                    ->action(function (Affiliate $record, array $data, AffiliateKycService $kycService): void {
                        /** @var User $user */
                        $user = auth()->user();

                        if ($data['decision'] === 'approve') {
                            $kycService->approve($record, $user);
                            Notification::make()->title('تم اعتماد وتوثيق هوية المسوق بنجاح')->success()->send();
                        } else {
                            $kycService->reject($record, $user, (string) $data['reject_reason']);
                            Notification::make()->title('تم رفض توثيق هوية المسوق وإخطاره بالسبب')->warning()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliates::route('/'),
            'edit' => EditAffiliate::route('/{record}/edit'),
        ];
    }
}
