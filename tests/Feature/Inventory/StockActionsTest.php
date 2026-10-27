<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Domain\Inventory\Actions\ApproveStockCountSessionAction;
use App\Domain\Inventory\Actions\InboundStockAction;
use App\Domain\Inventory\Actions\ReleaseStockAction;
use App\Domain\Inventory\Actions\ReserveStockAction;
use App\Domain\Inventory\Actions\ReturnStockAction;
use App\Domain\Inventory\Actions\ShipStockAction;
use App\Domain\Inventory\Actions\TransferStockAction;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\StockCountSession;
use App\Models\StockCountSessionItem;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class StockActionsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Warehouse $warehouseA;

    protected Warehouse $warehouseB;

    protected ProductVariant $variant;

    protected WholesalePrice $wholesalePrice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->ensureTenantExists('inv-tenant', 'inv-tenant.localhost');
        tenancy()->initialize($this->tenant);

        Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
        );

        $this->warehouseA = Warehouse::create([
            'name' => 'Main Cairo Warehouse',
            'code' => 'CAI-01',
            'type' => 'own',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Alexandria Warehouse',
            'code' => 'ALX-01',
            'type' => '3pl',
            'is_default' => false,
            'is_active' => true,
        ]);

        $productType = ProductType::create(['name' => 'Standard']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('Test Product')],
        ]);

        $taxClass = TaxClass::create(['name' => 'Standard Tax']);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-INV-001',
            'tax_class_id' => $taxClass->id,
            'stock' => 0,
        ]);

        $this->wholesalePrice = WholesalePrice::create([
            'product_variant_id' => $this->variant->id,
            'wholesale_price_minor' => 10000,
            'min_price_minor' => 15000,
            'max_price_minor' => 20000,
            'status' => WholesalePrice::STATUS_ACTIVE,
        ]);
    }

    public function test_inbound_stock_action_increments_balance_and_syncs_lunar(): void
    {
        $action = app(InboundStockAction::class);
        $balance = $action->execute(
            variantId: $this->variant->id,
            quantity: 10,
            warehouseId: $this->warehouseA->id,
            referenceType: 'supplier_receipt',
            referenceId: 'PO-100',
            referenceKey: 'po-100-variant-'.$this->variant->id
        );

        $this->assertEquals(10, $balance->on_hand);
        $this->assertEquals(0, $balance->reserved);
        $this->assertEquals(10, $balance->available);

        // Movement audit record created
        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouseA->id,
            'product_variant_id' => $this->variant->id,
            'quantity_delta' => 10,
            'reserved_delta' => 0,
            'reason' => 'inbound',
            'reference_key' => 'po-100-variant-'.$this->variant->id,
        ]);

        // Lunar ProductVariant stock is synchronized
        $this->assertEquals(10, $this->variant->fresh()->stock);

        // Idempotency: re-running with same referenceKey does not increment
        $action->execute(
            variantId: $this->variant->id,
            quantity: 10,
            warehouseId: $this->warehouseA->id,
            referenceType: 'supplier_receipt',
            referenceId: 'PO-100',
            referenceKey: 'po-100-variant-'.$this->variant->id
        );

        $this->assertEquals(10, $balance->fresh()->on_hand);
        $this->assertEquals(1, InventoryMovement::where('reference_key', 'po-100-variant-'.$this->variant->id)->count());
    }

    public function test_reserve_stock_action_and_insufficient_stock_exception(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 10, $this->warehouseA->id);

        $reserveAction = app(ReserveStockAction::class);
        $balance = $reserveAction->execute(
            variantId: $this->variant->id,
            quantity: 4,
            orderReference: 'ORD-001',
            warehouseId: $this->warehouseA->id
        );

        $this->assertEquals(10, $balance->on_hand);
        $this->assertEquals(4, $balance->reserved);
        $this->assertEquals(6, $balance->available);
        $this->assertEquals(6, $this->variant->fresh()->stock);

        // Idempotency check
        $balanceAgain = $reserveAction->execute(
            variantId: $this->variant->id,
            quantity: 4,
            orderReference: 'ORD-001',
            warehouseId: $this->warehouseA->id
        );
        $this->assertEquals(4, $balanceAgain->reserved);

        // Insufficient stock throws exception
        $this->expectException(InsufficientStockException::class);
        $reserveAction->execute(
            variantId: $this->variant->id,
            quantity: 7, // Only 6 available
            orderReference: 'ORD-002',
            warehouseId: $this->warehouseA->id
        );
    }

    public function test_release_stock_action_reduces_reserved_stock(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 10, $this->warehouseA->id);
        app(ReserveStockAction::class)->execute($this->variant->id, 4, 'ORD-001', $this->warehouseA->id);

        $releaseAction = app(ReleaseStockAction::class);
        $balance = $releaseAction->execute(
            variantId: $this->variant->id,
            quantity: 2,
            orderReference: 'ORD-001',
            warehouseId: $this->warehouseA->id
        );

        $this->assertEquals(10, $balance->on_hand);
        $this->assertEquals(2, $balance->reserved);
        $this->assertEquals(8, $balance->available);
        $this->assertEquals(8, $this->variant->fresh()->stock);

        // Movement audit
        $this->assertDatabaseHas('inventory_movements', [
            'reason' => 'release',
            'reserved_delta' => -2,
            'quantity_delta' => 0,
        ]);
    }

    public function test_ship_stock_action_permanently_deducts_on_hand_and_reserved(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 10, $this->warehouseA->id);
        app(ReserveStockAction::class)->execute($this->variant->id, 3, 'ORD-001', $this->warehouseA->id);

        $shipAction = app(ShipStockAction::class);
        $balance = $shipAction->execute(
            variantId: $this->variant->id,
            quantity: 3,
            orderReference: 'ORD-001',
            warehouseId: $this->warehouseA->id
        );

        $this->assertEquals(7, $balance->on_hand);
        $this->assertEquals(0, $balance->reserved);
        $this->assertEquals(7, $balance->available);
        $this->assertEquals(7, $this->variant->fresh()->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'reason' => 'ship',
            'quantity_delta' => -3,
            'reserved_delta' => -3,
        ]);
    }

    public function test_return_stock_action_restocks_sound_items(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 10, $this->warehouseA->id);
        app(ReserveStockAction::class)->execute($this->variant->id, 2, 'ORD-001', $this->warehouseA->id);
        app(ShipStockAction::class)->execute($this->variant->id, 2, 'ORD-001', $this->warehouseA->id);

        $returnAction = app(ReturnStockAction::class);

        // Sound item returned -> added to on_hand
        $balance = $returnAction->execute(
            variantId: $this->variant->id,
            quantity: 1,
            orderReference: 'ORD-001-A',
            isSound: true,
            warehouseId: $this->warehouseA->id
        );

        $this->assertEquals(9, $balance->on_hand);
        $this->assertEquals(9, $balance->available);

        // Damaged item returned -> not added to on_hand
        $balanceDamaged = $returnAction->execute(
            variantId: $this->variant->id,
            quantity: 1,
            orderReference: 'ORD-001-B',
            isSound: false,
            warehouseId: $this->warehouseA->id
        );

        $this->assertEquals(9, $balanceDamaged->on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'reason' => 'return_damaged',
            'quantity_delta' => 0,
        ]);
    }

    public function test_transfer_stock_action_moves_stock_between_warehouses(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 10, $this->warehouseA->id);

        $transferAction = app(TransferStockAction::class);
        $transferAction->execute(
            variantId: $this->variant->id,
            quantity: 4,
            fromWarehouseId: $this->warehouseA->id,
            toWarehouseId: $this->warehouseB->id,
            referenceId: 'TRF-001'
        );

        $balanceA = InventoryBalance::where('warehouse_id', $this->warehouseA->id)->where('product_variant_id', $this->variant->id)->first();
        $balanceB = InventoryBalance::where('warehouse_id', $this->warehouseB->id)->where('product_variant_id', $this->variant->id)->first();

        $this->assertEquals(6, $balanceA->on_hand);
        $this->assertEquals(4, $balanceB->on_hand);
        $this->assertEquals(10, $this->variant->fresh()->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouseA->id,
            'reason' => 'transfer_out',
            'quantity_delta' => -4,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouseB->id,
            'reason' => 'transfer_in',
            'quantity_delta' => 4,
        ]);
    }

    public function test_adjust_stock_action_and_protection_against_reserved_stock(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 10, $this->warehouseA->id);
        app(ReserveStockAction::class)->execute($this->variant->id, 5, 'ORD-001', $this->warehouseA->id);

        $adjustAction = app(AdjustStockAction::class);

        // Adjusting above reserved succeeds
        $balance = $adjustAction->execute(
            variantId: $this->variant->id,
            warehouseId: $this->warehouseA->id,
            newOnHand: 8
        );
        $this->assertEquals(8, $balance->on_hand);
        $this->assertEquals(5, $balance->reserved);
        $this->assertEquals(3, $balance->available);

        // Adjusting below reserved stock throws DomainException
        $this->expectException(DomainException::class);
        $adjustAction->execute(
            variantId: $this->variant->id,
            warehouseId: $this->warehouseA->id,
            newOnHand: 4 // Cannot set below 5 reserved
        );
    }

    public function test_eloquent_balance_check_constraint_prevents_corrupt_state(): void
    {
        $balance = new InventoryBalance([
            'warehouse_id' => $this->warehouseA->id,
            'product_variant_id' => $this->variant->id,
            'on_hand' => 5,
            'reserved' => 6, // Violates reserved <= on_hand
        ]);

        $this->expectException(DomainException::class);
        $balance->save();
    }

    public function test_inventory_movements_are_strictly_append_only(): void
    {
        $movement = InventoryMovement::create([
            'warehouse_id' => $this->warehouseA->id,
            'product_variant_id' => $this->variant->id,
            'quantity_delta' => 5,
            'reserved_delta' => 0,
            'reason' => 'inbound',
        ]);

        $this->expectException(LogicException::class);
        $movement->update(['notes' => 'attempting edit']);
    }

    public function test_inventory_movements_cannot_be_deleted(): void
    {
        $movement = InventoryMovement::create([
            'warehouse_id' => $this->warehouseA->id,
            'product_variant_id' => $this->variant->id,
            'quantity_delta' => 5,
            'reserved_delta' => 0,
            'reason' => 'inbound',
        ]);

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_stock_count_session_approval_reconciles_discrepancy(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 10, $this->warehouseA->id);

        $session = StockCountSession::create([
            'warehouse_id' => $this->warehouseA->id,
            'reference' => 'CNT-2026-001',
            'status' => 'draft',
        ]);

        StockCountSessionItem::create([
            'session_id' => $session->id,
            'product_variant_id' => $this->variant->id,
            'system_on_hand' => 10,
            'counted_on_hand' => 7,
            'discrepancy' => -3,
        ]);

        $approvalAction = app(ApproveStockCountSessionAction::class);
        $approvalAction->execute($session, approverId: 1);

        $this->assertEquals('approved', $session->fresh()->status);
        $balance = InventoryBalance::where('warehouse_id', $this->warehouseA->id)->where('product_variant_id', $this->variant->id)->first();
        $this->assertEquals(7, $balance->on_hand);
        $this->assertEquals(7, $this->variant->fresh()->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'reason' => 'count',
            'reference_type' => 'count_session',
            'quantity_delta' => -3,
        ]);
    }

    public function test_auto_pause_and_resume_when_available_stock_hits_zero(): void
    {
        // Initially active with 0 stock
        app(InboundStockAction::class)->execute($this->variant->id, 2, $this->warehouseA->id);
        $this->assertEquals(WholesalePrice::STATUS_ACTIVE, $this->wholesalePrice->fresh()->status);

        // Reserve all available stock -> available becomes 0
        app(ReserveStockAction::class)->execute($this->variant->id, 2, 'ORD-AUTO', $this->warehouseA->id);
        $this->assertEquals(0, $this->variant->fresh()->stock);

        // Product pricing is auto-paused with reason 'out_of_stock'
        $this->assertEquals(WholesalePrice::STATUS_PAUSED, $this->wholesalePrice->fresh()->status);
        $this->assertEquals('out_of_stock', $this->wholesalePrice->fresh()->pause_reason);

        // Inbound restock -> available becomes > 0
        app(InboundStockAction::class)->execute($this->variant->id, 5, $this->warehouseA->id);

        // Product pricing automatically resumes to active
        $this->assertEquals(WholesalePrice::STATUS_ACTIVE, $this->wholesalePrice->fresh()->status);
        $this->assertNull($this->wholesalePrice->fresh()->pause_reason);
    }
}
