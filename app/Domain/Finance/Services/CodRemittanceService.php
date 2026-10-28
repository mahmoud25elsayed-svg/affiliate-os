<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\DTOs\RemittanceImportRow;
use App\Domain\Finance\Enums\CodRemittanceStatus;
use App\Domain\Finance\Enums\RemittanceItemMatchStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\CodRemittance;
use App\Models\CodRemittanceItem;
use App\Models\CourierAccount;
use App\Models\Shipment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;

class CodRemittanceService
{
    /**
     * Parse remittance data rows, match with shipments, detect discrepancies, and create remittance records.
     *
     * @param  array<int, RemittanceImportRow|array<string, mixed>>  $rows
     */
    public function createRemittance(
        CourierAccount $courierAccount,
        array $rows,
        ?string $reference = null,
        ?User $creator = null,
        ?string $notes = null
    ): CodRemittance {
        if (empty($rows)) {
            throw new DomainException('لا يمكن إنشاء كشف تسوية بدون أسطر شحنات.');
        }

        $tenantId = $courierAccount->tenant_id ?? (tenant('id') ? (string) tenant('id') : 'default');
        $ref = $reference ?: 'REM-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        return DB::transaction(function () use ($courierAccount, $rows, $tenantId, $ref, $creator, $notes): CodRemittance {
            $totalCollectedMinor = 0;
            $totalShippingFeesMinor = 0;
            $matchedOrdersCount = 0;
            $discrepancyOrdersCount = 0;
            $discrepancyAmountMinor = 0;

            /** @var CodRemittance $remittance */
            $remittance = CodRemittance::create([
                'tenant_id' => $tenantId,
                'courier_account_id' => $courierAccount->id,
                'reference' => $ref,
                'status' => CodRemittanceStatus::DRAFT,
                'total_collected_minor' => 0,
                'total_shipping_fees_minor' => 0,
                'total_returned_fees_minor' => 0,
                'net_payout_minor' => 0,
                'matched_orders_count' => 0,
                'discrepancy_orders_count' => 0,
                'discrepancy_amount_minor' => 0,
                'notes' => $notes,
                'created_by' => $creator?->id,
            ]);

            foreach ($rows as $rawRow) {
                $row = $rawRow instanceof RemittanceImportRow ? $rawRow : RemittanceImportRow::fromArray($rawRow);
                if (empty($row->trackingNumber)) {
                    continue;
                }

                $totalCollectedMinor += $row->reportedCodMinor;
                $totalShippingFeesMinor += $row->shippingFeeMinor;

                /** @var Shipment|null $shipment */
                $shipment = Shipment::query()
                    ->where('tenant_id', $tenantId)
                    ->where('tracking_number', $row->trackingNumber)
                    ->with('order')
                    ->first();

                $order = $shipment?->order;
                $expectedCodMinor = 0;
                $expectedFeeMinor = 0;
                $matchStatus = RemittanceItemMatchStatus::MATCHED;
                $itemNote = $row->notes;

                if (! $shipment instanceof Shipment) {
                    $matchStatus = RemittanceItemMatchStatus::NOT_FOUND;
                    $discrepancyOrdersCount++;
                    $discrepancyAmountMinor += $row->reportedCodMinor;
                    $itemNote = $itemNote ?: 'رقم التتبع غير مسجل بالنظام';
                } elseif ($order instanceof Order && (string) $order->status === OrderStatus::SETTLED->value) {
                    $matchStatus = RemittanceItemMatchStatus::ALREADY_SETTLED;
                    $discrepancyOrdersCount++;
                    $itemNote = $itemNote ?: 'الطلب تمت تسويته مسبقاً في كشف سابق';
                } else {
                    $expectedCodMinor = $shipment->cod_amount_minor;
                    $expectedFeeMinor = $shipment->shipping_fees_minor;

                    $codDiff = $row->reportedCodMinor - $expectedCodMinor;
                    $feeDiff = $row->shippingFeeMinor - $expectedFeeMinor;

                    if ($codDiff !== 0) {
                        $matchStatus = RemittanceItemMatchStatus::AMOUNT_MISMATCH;
                        $discrepancyOrdersCount++;
                        $discrepancyAmountMinor += abs($codDiff);
                        $itemNote = $itemNote ?: "فارق تحصيل: المبلغ المحصل ({$row->reportedCodMinor}) يختلف عن المتوقع ({$expectedCodMinor})";
                    } elseif ($feeDiff !== 0) {
                        $matchStatus = RemittanceItemMatchStatus::FEE_MISMATCH;
                        $discrepancyOrdersCount++;
                        $discrepancyAmountMinor += abs($feeDiff);
                        $itemNote = $itemNote ?: "فارق مصاريف: المصاريف المفوترة ({$row->shippingFeeMinor}) تختلف عن المسجلة ({$expectedFeeMinor})";
                    } else {
                        $matchedOrdersCount++;
                    }
                }

                CodRemittanceItem::create([
                    'tenant_id' => $tenantId,
                    'cod_remittance_id' => $remittance->id,
                    'shipment_id' => $shipment?->id,
                    'order_id' => $order?->id,
                    'tracking_number' => $row->trackingNumber,
                    'reported_status' => $row->status,
                    'reported_cod_minor' => $row->reportedCodMinor,
                    'expected_cod_minor' => $expectedCodMinor,
                    'cod_discrepancy_minor' => $row->reportedCodMinor - $expectedCodMinor,
                    'shipping_fee_minor' => $row->shippingFeeMinor,
                    'expected_shipping_fee_minor' => $expectedFeeMinor,
                    'fee_discrepancy_minor' => $row->shippingFeeMinor - $expectedFeeMinor,
                    'match_status' => $matchStatus,
                    'notes' => $itemNote,
                ]);
            }

            $netPayoutMinor = $totalCollectedMinor - $totalShippingFeesMinor;
            $finalStatus = $discrepancyOrdersCount > 0
                ? CodRemittanceStatus::DISCREPANT
                : CodRemittanceStatus::RECONCILED;

            $remittance->update([
                'status' => $finalStatus,
                'total_collected_minor' => $totalCollectedMinor,
                'total_shipping_fees_minor' => $totalShippingFeesMinor,
                'net_payout_minor' => $netPayoutMinor,
                'matched_orders_count' => $matchedOrdersCount,
                'discrepancy_orders_count' => $discrepancyOrdersCount,
                'discrepancy_amount_minor' => $discrepancyAmountMinor,
            ]);

            return $remittance->fresh() ?? $remittance;
        });
    }
}
