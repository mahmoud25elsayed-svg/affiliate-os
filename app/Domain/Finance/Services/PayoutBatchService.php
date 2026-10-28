<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Models\PayoutBatch;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayoutBatchService
{
    public function __construct(
        protected PayoutService $payoutService
    ) {}

    /**
     * Create a batch from approved payout requests for a designated payment method.
     *
     * @param  list<int>  $requestIds
     */
    public function createBatch(string $paymentMethod, array $requestIds, User $creator): PayoutBatch
    {
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        /** @var Collection<int, PayoutRequest> $requests */
        $requests = PayoutRequest::whereIn('id', $requestIds)
            ->where('payment_method', $paymentMethod)
            ->whereIn('status', [PayoutRequest::STATUS_REQUESTED, PayoutRequest::STATUS_APPROVED])
            ->whereNull('batch_id')
            ->get();

        if ($requests->isEmpty()) {
            throw new InvalidArgumentException('لم يتم العثور على أي طلبات سحب صالحة مؤهلة للإدراج في الدفعة');
        }

        return DB::transaction(function () use ($tenantId, $paymentMethod, $requests, $creator) {
            $totalAmountMinor = (int) $requests->sum('amount_minor');
            $reference = 'BATCH-'.strtoupper(substr($paymentMethod, 0, 4)).'-'.date('YmdHis').'-'.rand(100, 999);

            /** @var PayoutBatch $batch */
            $batch = PayoutBatch::create([
                'tenant_id' => $tenantId,
                'batch_reference' => $reference,
                'payment_method' => $paymentMethod,
                'total_requests' => $requests->count(),
                'total_amount_minor' => $totalAmountMinor,
                'status' => PayoutBatch::STATUS_DRAFT,
                'created_by' => $creator->id,
            ]);

            PayoutRequest::whereIn('id', $requests->pluck('id'))->update([
                'batch_id' => $batch->id,
            ]);

            return $batch;
        });
    }

    /**
     * Generate CSV export tailored for the batch's specific payment channel.
     */
    public function exportBatchCsv(PayoutBatch $batch): string
    {
        $batch->load('requests.affiliate');
        $output = fopen('php://temp', 'r+');

        if ($output === false) {
            throw new InvalidArgumentException('فشل فتح مجرى ملف CSV المؤقت');
        }

        // Add UTF-8 BOM
        fwrite($output, "\xEF\xBB\xBF");

        if ($batch->payment_method === 'wallet_phone') {
            // Vodafone Cash / Mobile wallets format
            fputcsv($output, ['رقم الطلب', 'اسم المسوق', 'رقم المحفظة (فودافون كاش/أورانج/اتصالات)', 'مزود المحفظة', 'المبلغ (ج.م)', 'ملاحظات']);
            foreach ($batch->requests as $req) {
                $aff = $req->affiliate;
                /** @var array<string, mixed> $details */
                $details = $req->payout_details ?? [];
                $phone = $details['phone'] ?? ($aff ? $aff->phone : '');
                $provider = $details['wallet_provider'] ?? 'vodafone_cash';
                $amountEgp = number_format($req->amount_minor / 100, 2, '.', '');

                fputcsv($output, [
                    $req->id,
                    $aff ? $aff->name : '',
                    $phone,
                    $provider,
                    $amountEgp,
                    "سحب أرباح طلب رقم #{$req->id}",
                ]);
            }
        } elseif ($batch->payment_method === 'instapay') {
            // InstaPay format
            fputcsv($output, ['رقم الطلب', 'اسم المسوق', 'عنوان إنستاباي (IPA / Mobile)', 'المبلغ (ج.م)', 'ملاحظات']);
            foreach ($batch->requests as $req) {
                $aff = $req->affiliate;
                /** @var array<string, mixed> $details */
                $details = $req->payout_details ?? [];
                $handle = $details['instapay_handle'] ?? '';
                $amountEgp = number_format($req->amount_minor / 100, 2, '.', '');

                fputcsv($output, [
                    $req->id,
                    $aff ? $aff->name : '',
                    $handle,
                    $amountEgp,
                    "إنستاباي طلب #{$req->id}",
                ]);
            }
        } else {
            // Bank Transfer format
            fputcsv($output, ['رقم الطلب', 'اسم المستفيد', 'اسم البنك', 'رقم الحساب / IBAN', 'كود السويفت (SWIFT)', 'المبلغ (ج.م)']);
            foreach ($batch->requests as $req) {
                $aff = $req->affiliate;
                /** @var array<string, mixed> $details */
                $details = $req->payout_details ?? [];
                $bankName = $details['bank_name'] ?? '';
                $holder = $details['account_holder_name'] ?? ($aff ? $aff->name : '');
                $iban = $details['iban'] ?? '';
                $swift = $details['swift_code'] ?? '';
                $amountEgp = number_format($req->amount_minor / 100, 2, '.', '');

                fputcsv($output, [
                    $req->id,
                    $holder,
                    $bankName,
                    $iban,
                    $swift,
                    $amountEgp,
                ]);
            }
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }

    /**
     * Complete and execute all payout requests in a batch.
     */
    public function processBatch(
        PayoutBatch $batch,
        User $actor,
        string $batchReferenceNumber,
        ?string $receiptPath = null
    ): void {
        $batch->load('requests.affiliate');

        DB::transaction(function () use ($batch, $actor, $batchReferenceNumber, $receiptPath) {
            foreach ($batch->requests as $request) {
                if ($request->canBeExecuted()) {
                    $this->payoutService->executePayout(
                        request: $request,
                        actor: $actor,
                        referenceNumber: "{$batchReferenceNumber}-REQ{$request->id}",
                        receiptPath: $receiptPath
                    );
                }
            }

            $batch->update([
                'status' => PayoutBatch::STATUS_COMPLETED,
                'processed_by' => $actor->id,
                'processed_at' => now(),
                'notes' => "تم تنفيذ الدفعة برقم مرجعي: {$batchReferenceNumber}",
            ]);
        });
    }
}
