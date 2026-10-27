<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class InventoryReconcileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:reconcile
                            {--warehouse= : Filter by warehouse ID or code}
                            {--fix : Automatically correct balances to match movement audit trail}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and reconcile inventory balances against append-only movements audit trail';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting inventory audit & reconciliation...');

        $warehouseFilter = $this->option('warehouse');
        $autoFix = (bool) $this->option('fix');

        $query = InventoryBalance::query()->with(['warehouse', 'variant']);

        if ($warehouseFilter) {
            $query->whereHas('warehouse', function ($q) use ($warehouseFilter) {
                $q->where('id', $warehouseFilter)->orWhere('code', $warehouseFilter);
            });
        }

        $balances = $query->get();

        if ($balances->isEmpty()) {
            $this->warn('No inventory balances found to reconcile.');

            return self::SUCCESS;
        }

        $discrepancies = [];
        $totalChecked = 0;

        foreach ($balances as $balance) {
            $totalChecked++;

            // Sum of all quantity_delta in movements for this variant and warehouse
            $movementsSum = (int) InventoryMovement::query()
                ->where('warehouse_id', $balance->warehouse_id)
                ->where('product_variant_id', $balance->product_variant_id)
                ->sum('quantity_delta');

            // Sum of reserved_delta in movements
            $movementsReservedSum = (int) InventoryMovement::query()
                ->where('warehouse_id', $balance->warehouse_id)
                ->where('product_variant_id', $balance->product_variant_id)
                ->sum('reserved_delta');

            $onHandMismatch = ($movementsSum !== $balance->on_hand);
            $reservedMismatch = ($movementsReservedSum !== $balance->reserved);

            if ($onHandMismatch || $reservedMismatch) {
                $discrepancies[] = [
                    'balance_id' => $balance->id,
                    'warehouse' => $balance->warehouse instanceof Warehouse ? $balance->warehouse->name : (string) $balance->warehouse_id,
                    'variant_id' => $balance->product_variant_id,
                    'balance_on_hand' => $balance->on_hand,
                    'movements_on_hand' => $movementsSum,
                    'on_hand_diff' => $movementsSum - $balance->on_hand,
                    'balance_reserved' => $balance->reserved,
                    'movements_reserved' => $movementsReservedSum,
                    'reserved_diff' => $movementsReservedSum - $balance->reserved,
                ];

                if ($autoFix) {
                    $balance->on_hand = max(0, $movementsSum);
                    $balance->reserved = max(0, min($balance->on_hand, $movementsReservedSum));
                    $balance->save();
                    $this->info(sprintf(
                        'Fixed balance #%d: on_hand set to %d, reserved set to %d',
                        $balance->id,
                        $balance->on_hand,
                        $balance->reserved
                    ));
                }
            }
        }

        if (empty($discrepancies)) {
            $this->info(sprintf('All %d inventory balance records are 100%% consistent with movement audit trails.', $totalChecked));

            return self::SUCCESS;
        }

        $this->error(sprintf('Found %d discrepancies across %d checked balance records:', count($discrepancies), $totalChecked));

        $this->table(
            ['ID', 'Warehouse', 'Variant', 'Balance On-Hand', 'Movements On-Hand', 'Diff', 'Balance Res', 'Movements Res', 'Res Diff'],
            array_map(fn ($d) => [
                $d['balance_id'],
                $d['warehouse'],
                $d['variant_id'],
                $d['balance_on_hand'],
                $d['movements_on_hand'],
                $d['on_hand_diff'],
                $d['balance_reserved'],
                $d['movements_reserved'],
                $d['reserved_diff'],
            ], $discrepancies)
        );

        if ($autoFix) {
            $this->info('All discrepancies have been automatically corrected based on audit trail movements.');

            return self::SUCCESS;
        }

        $this->warn('Run with --fix to automatically correct balances to match movements.');

        return self::FAILURE;
    }
}
