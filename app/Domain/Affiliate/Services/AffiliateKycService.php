<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Models\Affiliate;
use App\Models\KycAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class AffiliateKycService
{
    public function submitDocuments(
        Affiliate $affiliate,
        string $nationalId,
        UploadedFile $front,
        UploadedFile $back
    ): void {
        $nationalId = trim($nationalId);
        if (strlen($nationalId) < 10) {
            throw new InvalidArgumentException(__('الرقم القومي المدخل غير صحيح. يجب أن يتكون من 14 رقم للبطاقة المصرية.'));
        }

        $tenantId = $affiliate->tenant_id;
        $folder = "kyc/{$tenantId}/{$affiliate->id}";

        $frontPath = $front->store($folder, 'local');
        $backPath = $back->store($folder, 'local');

        $affiliate->update([
            'national_id_number' => $nationalId,
            'id_card_front_path' => $frontPath,
            'id_card_back_path' => $backPath,
            'kyc_status' => Affiliate::KYC_PENDING,
            'kyc_rejected_reason' => null,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function viewDocument(
        Affiliate $affiliate,
        string $side,
        User $viewer,
        ?string $ip = null,
        ?string $userAgent = null
    ): string {
        // Enforce kyc.view permission check
        if (! $this->canViewKyc($viewer)) {
            throw new AuthorizationException(__('ليس لديك الصلاحية المطلوبة (kyc.view) لعرض وثائق الهوية الشخصية للمسوق.'));
        }

        $path = $side === 'front' ? $affiliate->id_card_front_path : $affiliate->id_card_back_path;

        if (empty($path) || ! Storage::disk('local')->exists($path)) {
            throw new InvalidArgumentException(__('مستند الهوية المطلوب (:side) غير متوفر أو تم حذفه.', ['side' => $side]));
        }

        // Record Mandatory Audit Trail
        KycAuditLog::create([
            'tenant_id' => $affiliate->tenant_id,
            'affiliate_id' => $affiliate->id,
            'user_id' => $viewer->id,
            'document_type' => $side,
            'action' => 'view',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return Storage::disk('local')->path($path);
    }

    public function approve(Affiliate $affiliate, User $reviewer): void
    {
        $affiliate->update([
            'kyc_status' => Affiliate::KYC_APPROVED,
            'kyc_rejected_reason' => null,
            'kyc_reviewed_at' => Carbon::now(),
            'kyc_reviewed_by' => $reviewer->id,
        ]);

        KycAuditLog::create([
            'tenant_id' => $affiliate->tenant_id,
            'affiliate_id' => $affiliate->id,
            'user_id' => $reviewer->id,
            'document_type' => 'both',
            'action' => 'approve',
        ]);
    }

    public function reject(Affiliate $affiliate, User $reviewer, string $reason): void
    {
        $affiliate->update([
            'kyc_status' => Affiliate::KYC_REJECTED,
            'kyc_rejected_reason' => $reason,
            'kyc_reviewed_at' => Carbon::now(),
            'kyc_reviewed_by' => $reviewer->id,
        ]);

        KycAuditLog::create([
            'tenant_id' => $affiliate->tenant_id,
            'affiliate_id' => $affiliate->id,
            'user_id' => $reviewer->id,
            'document_type' => 'both',
            'action' => 'reject',
        ]);
    }

    protected function canViewKyc(User $user): bool
    {
        // Check if user has permission kyc.view or has owner / admin role
        if ($user->hasPermissionTo('kyc.view')) {
            return true;
        }

        if ($user->hasRole('owner') || $user->hasRole('manager') || $user->hasRole('super_admin')) {
            return true;
        }

        return false;
    }
}
