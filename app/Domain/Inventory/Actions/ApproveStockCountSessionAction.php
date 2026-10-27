<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Models\StockCountSession;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApproveStockCountSessionAction
{
    public function __construct(
        protected readonly AdjustStockAction $adjustStockAction
    ) {}

    public function execute(
        StockCountSession $session,
        int $approverId,
        ?string $notes = null
    ): StockCountSession {
        if ($session->status !== 'draft') {
            throw new DomainException(sprintf('Cannot approve session in "%s" status.', $session->status));
        }

        return DB::transaction(function () use ($session, $approverId, $notes): StockCountSession {
            $session->load('items');

            foreach ($session->items as $item) {
                if ($item->discrepancy !== 0) {
                    $this->adjustStockAction->execute(
                        variantId: (int) $item->product_variant_id,
                        warehouseId: (int) $session->warehouse_id,
                        newOnHand: (int) $item->counted_on_hand,
                        reason: 'count',
                        referenceType: 'count_session',
                        referenceId: $session->reference,
                        notes: sprintf('Stock count session #%s discrepancy correction.', $session->reference),
                        actorType: 'staff',
                        actorId: (string) $approverId
                    );
                }
            }

            $session->update([
                'status' => 'approved',
                'approver_id' => $approverId,
                'approved_at' => Carbon::now(),
                'notes' => $notes ?? $session->notes,
            ]);

            return $session;
        });
    }
}
