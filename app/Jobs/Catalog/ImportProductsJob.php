<?php

declare(strict_types=1);

namespace App\Jobs\Catalog;

use App\Domain\Catalog\Services\ProductService;
use App\Models\Tenant;
use App\Support\Billing\Exceptions\LimitExceededException;
use App\Support\Billing\Limits;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array{total: int, imported: int, failed: int, errors: array<int, string>}
     */
    public array $report = [
        'total' => 0,
        'imported' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    public function __construct(
        public string $csvPath,
        public Tenant $tenant
    ) {}

    /**
     * @return array{total: int, imported: int, failed: int, errors: array<int, string>}
     */
    public function handle(ProductService $productService): array
    {
        if (! file_exists($this->csvPath) || ! is_readable($this->csvPath)) {
            $this->report['errors'][0] = 'CSV file does not exist or is not readable.';

            return $this->report;
        }

        $handle = fopen($this->csvPath, 'r');
        if (! $handle) {
            $this->report['errors'][0] = 'Failed to open CSV file.';

            return $this->report;
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            $this->report['errors'][0] = 'CSV file is empty.';

            return $this->report;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $rowNumber = 1; // row 1 is header

        $this->tenant->run(function () use ($handle, $header, &$rowNumber, $productService) {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) === 1 && empty($row[0])) {
                    continue; // Skip empty lines
                }

                $this->report['total']++;

                /** @var array<string, string> $data */
                $data = [];
                foreach ($header as $index => $key) {
                    $data[$key] = isset($row[$index]) ? trim((string) $row[$index]) : '';
                }

                $name = $data['name'] ?? '';
                $sku = $data['sku'] ?? '';

                if (empty($name) || empty($sku)) {
                    $this->report['failed']++;
                    $this->report['errors'][$rowNumber] = "الصف {$rowNumber}: اسم المنتج ورمز SKU مطلوبان.";

                    continue;
                }

                // Check plan limit before attempting creation
                if (! Limits::check('products', $this->tenant)) {
                    $this->report['failed']++;
                    $max = Limits::get('products', $this->tenant);
                    $this->report['errors'][$rowNumber] = "الصف {$rowNumber}: تم تجاوز الحد الأقصى للمنتجات المسموح بها في خطتك ({$max} منتجات).";

                    continue;
                }

                try {
                    // Prices conversion (decimal string to minor units)
                    $wholesale = ! empty($data['wholesale_price']) ? Money::fromDecimalString($data['wholesale_price'])->amount : 0;
                    $min = ! empty($data['min_price']) ? Money::fromDecimalString($data['min_price'])->amount : $wholesale;
                    $max = ! empty($data['max_price']) ? Money::fromDecimalString($data['max_price'])->amount : $min;
                    $retail = ! empty($data['retail_price']) ? Money::fromDecimalString($data['retail_price'])->amount : $min;
                    $compareAt = ! empty($data['compare_at_price']) ? Money::fromDecimalString($data['compare_at_price'])->amount : null;

                    $productService->createProduct([
                        'name' => $name,
                        'sku' => $sku,
                        'wholesale_price' => $wholesale,
                        'min_price' => $min,
                        'max_price' => $max,
                        'retail_price' => $retail,
                        'compare_at_price' => $compareAt,
                        'status' => $data['status'] ?? 'active',
                        'pause_reason' => $data['pause_reason'] ?? null,
                    ], $this->tenant);

                    $this->report['imported']++;
                } catch (LimitExceededException $e) {
                    $this->report['failed']++;
                    $this->report['errors'][$rowNumber] = "الصف {$rowNumber}: استنفدت باقتك الحد المسموح به للمنتجات.";
                } catch (Throwable $e) {
                    $this->report['failed']++;
                    $this->report['errors'][$rowNumber] = "الصف {$rowNumber}: ".$e->getMessage();
                }
            }
        });

        fclose($handle);

        return $this->report;
    }
}
