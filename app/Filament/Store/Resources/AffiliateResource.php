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

    public static function getNavigationLabel(): string
    {

        return __('المسوقون (الأفلييت)');

    }

    public static function getModelLabel(): string
    {
        return __('مسوق');
    }

    public static function getPluralModelLabel(): string
    {
        return __('المسوقون والأفلييت');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('برنامج الأفلييت');
    }

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('البيانات الأساسية'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('الاسم بالكامل'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label(__('رقم الهاتف'))
                        ->disabled()
                        ->required(),

                    TextInput::make('email')
                        ->label(__('البريد الإلكتروني'))
                        ->email()
                        ->nullable(),

                    TextInput::make('code')
                        ->label(__('كود الإحالة الفريد'))
                        ->disabled(),
                ]),
            ]),

            Section::make(__('حالة الحساب والعمولة'))->schema([
                Grid::make(2)->schema([
                    Select::make('status')
                        ->label(__('حالة الحساب'))
                        ->options([
                            Affiliate::STATUS_PENDING => __('قيد الانتظار والمراجعة'),
                            Affiliate::STATUS_ACTIVE => __('نشط ومفعل'),
                            Affiliate::STATUS_SUSPENDED => __('معلق / موقوف مؤقتاً'),
                            Affiliate::STATUS_REJECTED => __('مرفوض'),
                        ])
                        ->required(),

                    TextInput::make('commission_rate')
                        ->label(__('نسبة العمولة الافتراضية (%)'))
                        ->numeric()
                        ->default(10.00),

                    Textarea::make('suspension_reason')
                        ->label(__('سبب التعليق / الإيقاف'))
                        ->rows(2)
                        ->columnSpanFull()
                        ->visible(fn ($get) => $get('status') === Affiliate::STATUS_SUSPENDED),
                ]),
            ]),

            Section::make(__('توثيق الهوية (KYC)'))->schema([
                Grid::make(2)->schema([
                    Select::make('kyc_status')
                        ->label(__('حالة التوثيق'))
                        ->options([
                            Affiliate::KYC_PENDING => __('قيد المراجعة'),
                            Affiliate::KYC_APPROVED => __('تم التوثيق والاعتماد'),
                            Affiliate::KYC_REJECTED => __('مرفوض'),
                        ])
                        ->disabled(),

                    TextInput::make('national_id_number')
                        ->label(__('الرقم القومي (14 رقم)'))
                        ->disabled(),

                    Textarea::make('kyc_rejected_reason')
                        ->label(__('سبب رفض التوثيق'))
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
                    ->label(__('اسم المسوق'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label(__('رقم الهاتف'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('code')
                    ->label(__('كود الإحالة'))
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('commission_rate')
                    ->label(__('نسبة العمولة'))
                    ->formatStateUsing(fn ($state) => $state ? "{$state}%" : __('افتراضي'))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('حالة الحساب'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Affiliate::STATUS_ACTIVE => __('نشط'),
                        Affiliate::STATUS_PENDING => __('قيد الانتظار'),
                        Affiliate::STATUS_SUSPENDED => __('معلق'),
                        Affiliate::STATUS_REJECTED => __('مرفوض'),
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
                    ->label(__('توثيق KYC'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Affiliate::KYC_APPROVED => __('موثق'),
                        Affiliate::KYC_PENDING => __('قيد الفحص'),
                        Affiliate::KYC_REJECTED => __('مرفوض'),
                        default => __('غير مقدم'),
                    })
                    ->color(fn (?string $state) => match ($state) {
                        Affiliate::KYC_APPROVED => 'success',
                        Affiliate::KYC_PENDING => 'warning',
                        Affiliate::KYC_REJECTED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('payout_security')
                    ->label(__('أمان السحب'))
                    ->state(function (Affiliate $record): string {
                        if ($record->payouts_frozen_until && $record->payouts_frozen_until->isFuture()) {
                            return __('سحب مجمد حتى ').$record->payouts_frozen_until->format('m/d H:i');
                        }

                        return __('نشط');
                    })
                    ->badge()
                    ->color(fn (string $state) => str_contains($state, rtrim(__('سحب مجمد حتى '))) ? 'danger' : 'success'),

                TextColumn::make('created_at')
                    ->label(__('تاريخ الانضمام'))
                    ->dateTime('Y-m-d')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('تصفية حسب الحالة'))
                    ->options([
                        Affiliate::STATUS_ACTIVE => __('نشط'),
                        Affiliate::STATUS_PENDING => __('قيد الانتظار'),
                        Affiliate::STATUS_SUSPENDED => __('معلق'),
                        Affiliate::STATUS_REJECTED => __('مرفوض'),
                    ]),

                SelectFilter::make('kyc_status')
                    ->label(__('حالة التوثيق'))
                    ->options([
                        Affiliate::KYC_APPROVED => __('موثق'),
                        Affiliate::KYC_PENDING => __('قيد الفحص'),
                        Affiliate::KYC_REJECTED => __('مرفوض'),
                    ]),
            ])
            ->actions([
                Action::make('approve_account')
                    ->label(__('تفعيل المسوق'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Affiliate $record) => $record->status === Affiliate::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (Affiliate $record): void {
                        $record->update(['status' => Affiliate::STATUS_ACTIVE]);
                        Notification::make()->title(__('تم تفعيل حساب المسوق بنجاح'))->success()->send();
                    }),

                Action::make('suspend_account')
                    ->label(__('تعليق الحساب'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Affiliate $record) => $record->status === Affiliate::STATUS_ACTIVE)
                    ->form([
                        Textarea::make('reason')
                            ->label(__('سبب تعليق الحساب'))
                            ->required(),
                    ])
                    ->action(function (Affiliate $record, array $data): void {
                        $record->update([
                            'status' => Affiliate::STATUS_SUSPENDED,
                            'suspension_reason' => $data['reason'],
                        ]);
                        Notification::make()->title(__('تم تعليق حساب المسوق'))->warning()->send();
                    }),

                Action::make('review_kyc')
                    ->label(__('فحص وثائق KYC'))
                    ->icon('heroicon-o-identification')
                    ->color('primary')
                    ->visible(fn (Affiliate $record) => ! empty($record->national_id_number))
                    ->modalHeading(__('فحص وتدقيق وثائق الهوية الوطنية'))
                    ->modalDescription(__('تنبيه: يتم تسجيل هذا الإجراء في سجل التدقيق KYC Audit Trail تلقائياً.'))
                    ->form(function (Affiliate $record) {
                        $frontUrl = route('store.affiliates.kyc-document', ['affiliate' => $record->id, 'side' => 'front']);
                        $backUrl = route('store.affiliates.kyc-document', ['affiliate' => $record->id, 'side' => 'back']);

                        return [
                            Placeholder::make('kyc_details')
                                ->label(__('بيانات المسوق'))
                                ->content(new HtmlString(__('
                                    <div class=\'space-y-3 text-sm\'>
                                        <div><strong>الاسم:</strong> :name</div>
                                        <div><strong>رقم الهاتف:</strong> :phone</div>
                                        <div><strong>الرقم القومي:</strong> <span class=\'font-mono font-bold text-primary-600\'>:national_id_number</span></div>
                                        <div class=\'grid grid-cols-2 gap-4 mt-4\'>
                                            <div class=\'border rounded p-3 text-center\'>
                                                <p class=\'font-bold mb-2\'>وجه البطاقة الأمامي</p>
                                                <a href=\':front_url\' target=\'_blank\' class=\'inline-block bg-primary-50 text-primary-600 px-3 py-1 rounded text-xs font-semibold hover:bg-primary-100\'>فتح المستند الأمامي بأمان &larr;</a>
                                            </div>
                                            <div class=\'border rounded p-3 text-center\'>
                                                <p class=\'font-bold mb-2\'>ظهر البطاقة الخلفي</p>
                                                <a href=\':back_url\' target=\'_blank\' class=\'inline-block bg-primary-50 text-primary-600 px-3 py-1 rounded text-xs font-semibold hover:bg-primary-100\'>فتح المستند الخلفي بأمان &larr;</a>
                                            </div>
                                        </div>
                                    </div>
                                ', ['name' => $record->name, 'phone' => $record->phone, 'national_id_number' => $record->national_id_number, 'front_url' => $frontUrl, 'back_url' => $backUrl]))),

                            Select::make('decision')
                                ->label(__('قرار المراجعة'))
                                ->options([
                                    'approve' => __('اعتماد وتوثيق الهوية (Approved)'),
                                    'reject' => __('رفض التوثيق (Rejected)'),
                                ])
                                ->required(),

                            Textarea::make('reject_reason')
                                ->label(__('سبب الرفض (في حالة الرفض)'))
                                ->visible(fn ($get) => $get('decision') === 'reject')
                                ->required(fn ($get) => $get('decision') === 'reject'),
                        ];
                    })
                    ->action(function (Affiliate $record, array $data, AffiliateKycService $kycService): void {
                        /** @var User $user */
                        $user = auth()->user();

                        if ($data['decision'] === 'approve') {
                            $kycService->approve($record, $user);
                            Notification::make()->title(__('تم اعتماد وتوثيق هوية المسوق بنجاح'))->success()->send();
                        } else {
                            $kycService->reject($record, $user, (string) $data['reject_reason']);
                            Notification::make()->title(__('تم رفض توثيق هوية المسوق وإخطاره بالسبب'))->warning()->send();
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
