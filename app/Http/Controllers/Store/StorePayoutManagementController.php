<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Finance\Exceptions\MakerCheckerViolationException;
use App\Domain\Finance\Services\PayoutBatchService;
use App\Domain\Finance\Services\PayoutService;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Http\Controllers\Controller;
use App\Models\PayoutBatch;
use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StorePayoutManagementController extends Controller
{
    public function __construct(
        protected PayoutService $payoutService,
        protected PayoutBatchService $batchService,
        protected PayoutTurnaroundCalculator $turnaroundCalculator
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $query = PayoutRequest::query()->with(['affiliate', 'approver', 'payer', 'batch'])->latest('id');

        if ($status && in_array($status, [
            PayoutRequest::STATUS_REQUESTED,
            PayoutRequest::STATUS_APPROVED,
            PayoutRequest::STATUS_PAID,
            PayoutRequest::STATUS_REJECTED,
            PayoutRequest::STATUS_CANCELLED,
        ], true)) {
            $query->where('status', $status);
        }

        $payoutRequests = $query->paginate(20);

        // Summaries
        $requestedCount = PayoutRequest::where('status', PayoutRequest::STATUS_REQUESTED)->count();
        $approvedCount = PayoutRequest::where('status', PayoutRequest::STATUS_APPROVED)->count();
        $paidCount = PayoutRequest::where('status', PayoutRequest::STATUS_PAID)->count();
        $totalPaidMinor = (int) PayoutRequest::where('status', PayoutRequest::STATUS_PAID)->sum('amount_minor');

        $batches = PayoutBatch::query()->latest('id')->take(10)->get();

        return view('store.payouts.index', [
            'payoutRequests' => $payoutRequests,
            'currentStatus' => $status,
            'requestedCount' => $requestedCount,
            'approvedCount' => $approvedCount,
            'paidCount' => $paidCount,
            'totalPaidMinor' => $totalPaidMinor,
            'averageTurnaround' => $this->turnaroundCalculator->getAverageTurnaroundHuman(),
            'batches' => $batches,
        ]);
    }

    public function approve(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($actor->hasRole('call_center') || $actor->hasRole('orders')) {
            abort(403, 'غير مصرح لك باعتماد طلبات السحب.');
        }

        try {
            $this->payoutService->approvePayout($payoutRequest, $actor);

            return back()->with('success', "تم اعتماد طلب السحب رقم #{$payoutRequest->id} بنجاح.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function execute(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($actor->hasRole('call_center') || $actor->hasRole('orders')) {
            abort(403, 'غير مصرح لك بتنفيذ طلبات السحب.');
        }

        $rules = [
            'reference_number' => ['required', 'string', 'max:100'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];

        if ($actor->hasTwoFactorEnabled()) {
            $rules['two_factor_code'] = ['required', 'string'];
        }

        $request->validate($rules, [
            'reference_number.required' => 'الرقم المرجعي للتحويل مطلوب',
            'two_factor_code.required' => 'رمز التحقق بخطوتين مطلوب لتنفيذ عملية الصرف.',
        ]);

        $referenceNumber = (string) $request->input('reference_number');
        $twoFactorCode = $request->filled('two_factor_code') ? (string) $request->input('two_factor_code') : null;
        $receiptPath = null;
        $receiptFile = $request->file('receipt');
        if ($receiptFile) {
            $path = $receiptFile->store('payout_receipts', 'public');
            $receiptPath = $path ? (string) $path : null;
        }

        try {
            $this->payoutService->executePayout(
                request: $payoutRequest,
                actor: $actor,
                referenceNumber: $referenceNumber,
                receiptPath: $receiptPath,
                twoFactorCode: $twoFactorCode
            );

            return back()->with('success', "تم تنفيذ تحويل طلب السحب رقم #{$payoutRequest->id} بنجاح وقيد العملية بالدفتر المالي.");
        } catch (MakerCheckerViolationException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, PayoutRequest $payoutRequest): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'سبب الرفض مطلوب لإشعار المسوق وإعادة الرصيد',
        ]);

        try {
            $this->payoutService->rejectPayout(
                request: $payoutRequest,
                actor: $actor,
                reason: (string) $request->input('reason')
            );

            return back()->with('success', "تم رفض طلب السحب رقم #{$payoutRequest->id} وإعادة المبلغ إلى الرصيد المتاح للمسوق.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function createBatch(Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $request->validate([
            'payment_method' => ['required', 'string'],
            'request_ids' => ['required', 'array'],
            'request_ids.*' => ['integer'],
        ]);

        try {
            /** @var list<int> $ids */
            $ids = array_map('intval', $request->input('request_ids'));
            $batch = $this->batchService->createBatch(
                paymentMethod: (string) $request->input('payment_method'),
                requestIds: $ids,
                creator: $actor
            );

            return back()->with('success', "تم إنشاء دفعة السحب {$batch->batch_reference} بنجاح.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function exportBatch(PayoutBatch $batch): Response
    {
        $csvContent = $this->batchService->exportBatchCsv($batch);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"payout_batch_{$batch->batch_reference}.csv\"",
        ]);
    }

    public function processBatch(Request $request, PayoutBatch $batch): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $request->validate([
            'batch_reference_number' => ['required', 'string', 'max:100'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $receiptPath = null;
        $batchFile = $request->file('receipt');
        if ($batchFile) {
            $path = $batchFile->store('payout_receipts', 'public');
            $receiptPath = $path ? (string) $path : null;
        }

        try {
            $this->batchService->processBatch(
                batch: $batch,
                actor: $actor,
                batchReferenceNumber: (string) $request->input('batch_reference_number'),
                receiptPath: $receiptPath
            );

            return back()->with('success', "تم تنفيذ دفعة السحب بالكامل برقم مرجعي: {$request->input('batch_reference_number')}");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
