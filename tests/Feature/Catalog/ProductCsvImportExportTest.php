<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Jobs\Catalog\ExportProductsJob;
use App\Jobs\Catalog\ImportProductsJob;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Product;
use Tests\TestCase;

class ProductCsvImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_can_import_csv_and_generate_row_by_row_report(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('catalog-import-test');

        // Give tenant enterprise plan with unlimited products
        /** @var Plan $enterprisePlan */
        $enterprisePlan = Plan::where('slug', 'enterprise')->firstOrFail();
        $tenant->subscription->update(['plan_id' => $enterprisePlan->id]);

        $tenant->run(fn () => Product::query()->delete());

        // Create temporary CSV with 500 rows + some invalid rows to test error reporting
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_import_');
        $handle = fopen($tempFile, 'w');
        $this->assertNotFalse($handle);

        fputcsv($handle, [
            'name',
            'sku',
            'retail_price',
            'wholesale_price',
            'min_price',
            'max_price',
            'compare_at_price',
            'status',
            'pause_reason',
        ]);

        // 500 valid rows
        for ($i = 1; $i <= 500; $i++) {
            fputcsv($handle, [
                "Bulk Product {$i}",
                "SKU-BULK-{$i}",
                '150.00',
                '80.00',
                '100.00',
                '200.00',
                '180.00',
                'active',
                '',
            ]);
        }

        // 2 invalid rows at the end:
        // Invalid 1: missing SKU
        fputcsv($handle, ['Bad Product No SKU', '', '100.00', '50.00', '80.00', '120.00', '', 'active', '']);
        // Invalid 2: min < wholesale
        fputcsv($handle, ['Bad Product Price Range', 'SKU-BAD-RANGE', '100.00', '90.00', '70.00', '120.00', '', 'active', '']);

        fclose($handle);

        $job = new ImportProductsJob($tempFile, $tenant);
        $report = app()->call([$job, 'handle']);

        $this->assertEquals(502, $report['total']);
        $this->assertEquals(500, $report['imported']);
        $this->assertEquals(2, $report['failed']);
        $this->assertArrayHasKey(502, $report['errors']); // row 502 (header + 500 rows + 1)
        $this->assertArrayHasKey(503, $report['errors']); // row 503

        $tenant->run(function () {
            $this->assertEquals(500, Product::count());
        });

        // Test Export
        $exportJob = new ExportProductsJob($tenant);
        $csvContent = $exportJob->handle();

        $this->assertNotEmpty($csvContent);
        $this->assertStringContainsString('name,sku,retail_price,wholesale_price,min_price,max_price', $csvContent);
        $this->assertStringContainsString('Bulk Product 1', $csvContent);
        $this->assertStringContainsString('SKU-BULK-1', $csvContent);
        $this->assertStringContainsString('80.00', $csvContent);
        $this->assertStringContainsString('100.00', $csvContent);

        @unlink($tempFile);
    }

    public function test_csv_import_strictly_stops_when_plan_limit_is_exhausted(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('catalog-limit-import');

        // Create a custom plan limited to 5 products
        $limitedPlan = Plan::create([
            'slug' => 'custom-limit-5',
            'name_ar' => 'خطة 5 منتجات',
            'name_en' => 'Limit 5 Plan',
            'price_monthly' => 5000,
            'price_yearly' => 50000,
            'trial_days' => 14,
            'limits' => ['products' => 5],
            'features' => ['affiliate' => true],
            'is_active' => true,
        ]);

        $tenant->subscription->update(['plan_id' => $limitedPlan->id]);
        $tenant->run(fn () => Product::query()->delete());

        // Create CSV with 10 rows
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_limit_');
        $handle = fopen($tempFile, 'w');
        $this->assertNotFalse($handle);

        fputcsv($handle, ['name', 'sku', 'retail_price', 'wholesale_price', 'min_price', 'max_price', 'compare_at_price', 'status']);
        for ($i = 1; $i <= 10; $i++) {
            fputcsv($handle, ["Product {$i}", "SKU-LIMIT-{$i}", '100.00', '50.00', '70.00', '150.00', '', 'active']);
        }
        fclose($handle);

        $job = new ImportProductsJob($tempFile, $tenant);
        $report = app()->call([$job, 'handle']);

        $this->assertEquals(10, $report['total']);
        $this->assertEquals(5, $report['imported']);
        $this->assertEquals(5, $report['failed']);

        // Check error messages indicate plan limit
        $this->assertStringContainsString('تم تجاوز الحد الأقصى للمنتجات', $report['errors'][7]);

        $tenant->run(function () {
            $this->assertEquals(5, Product::count());
        });

        @unlink($tempFile);
    }
}
