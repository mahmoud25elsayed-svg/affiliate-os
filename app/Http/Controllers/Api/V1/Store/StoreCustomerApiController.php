<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use App\Domain\Compliance\Services\CustomerDataAnonymizationService;
use App\Domain\Compliance\Services\CustomerDataExportService;
use App\Http\Controllers\Controller;
use App\Models\Customer as AppCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCustomerApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        // Fetch customers across store
        $customers = AppCustomer::query()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Export customer personal data in machine-readable JSON (Right to Data Portability).
     */
    public function export(int $id, CustomerDataExportService $exportService): JsonResponse
    {
        /** @var AppCustomer $customer */
        $customer = AppCustomer::findOrFail($id);
        $data = $exportService->exportCustomerData($customer);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Anonymize customer personal data while preserving financial invariants (Right to Erasure).
     */
    public function anonymize(int $id, CustomerDataAnonymizationService $anonymizationService): JsonResponse
    {
        /** @var AppCustomer $customer */
        $customer = AppCustomer::findOrFail($id);
        $anonymizationService->anonymizeCustomer($customer);

        return response()->json([
            'status' => 'success',
            'message' => 'Customer personal data has been successfully anonymized per Right to Erasure.',
        ]);
    }
}
