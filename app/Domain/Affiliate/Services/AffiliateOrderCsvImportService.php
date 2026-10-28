<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Actions\SubmitAffiliateOrderAction;
use App\Models\Affiliate;
use App\Support\Location\Governorate;
use DomainException;
use Illuminate\Http\UploadedFile;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Throwable;

class AffiliateOrderCsvImportService
{
    public function __construct(
        protected SubmitAffiliateOrderAction $submitOrderAction
    ) {}

    /**
     * @return array{
     *     total: int,
     *     imported: int,
     *     failed: int,
     *     orders: list<Order>,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public function import(Affiliate $affiliate, UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new DomainException('تعذر قراءة ملف CSV المرفق.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            throw new DomainException('ملف CSV فارغ أو لا يحتوي على صف عناوين صالح.');
        }

        // Normalize header row (strip BOM and trim)
        $mappedColumns = $this->mapHeaderColumns($header);

        $rowNumber = 1;
        $importedCount = 0;
        $failedCount = 0;
        $orders = [];
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($row, fn ($val) => trim((string) $val) !== ''))) {
                continue;
            }

            try {
                $orderData = $this->parseRow($row, $mappedColumns, $rowNumber);
                $order = $this->submitOrderAction->execute($affiliate, $orderData);

                $orders[] = $order;
                $importedCount++;
            } catch (Throwable $e) {
                $failedCount++;
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ];
            }
        }

        fclose($handle);

        return [
            'total' => $importedCount + $failedCount,
            'imported' => $importedCount,
            'failed' => $failedCount,
            'orders' => $orders,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    protected function mapHeaderColumns(array $header): array
    {
        $map = [];
        foreach ($header as $index => $col) {
            if ($col === null) {
                continue;
            }
            $cleanCol = strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col) ?? ''));
            $original = trim($col);

            if (in_array($cleanCol, ['name', 'customer_name', 'customer', 'الاسم', 'اسم العميل'], true) || in_array($original, ['الاسم', 'اسم العميل'], true)) {
                $map['customer_name'] = $index;
            } elseif (in_array($cleanCol, ['phone', 'customer_phone', 'mobile', 'الهاتف', 'رقم الهاتف'], true) || in_array($original, ['الهاتف', 'رقم الهاتف'], true)) {
                $map['customer_phone'] = $index;
            } elseif (in_array($cleanCol, ['governorate', 'gov', 'governorate_id', 'المحافظة'], true) || in_array($original, ['المحافظة', 'كود المحافظة'], true)) {
                $map['governorate'] = $index;
            } elseif (in_array($cleanCol, ['city', 'المدينة', 'المركز'], true) || in_array($original, ['المدينة', 'المركز'], true)) {
                $map['city'] = $index;
            } elseif (in_array($cleanCol, ['address', 'العنوان', 'العنوان بالتفصيل'], true) || in_array($original, ['العنوان', 'العنوان بالتفصيل'], true)) {
                $map['address'] = $index;
            } elseif (in_array($cleanCol, ['sku', 'variant_id', 'code', 'كود المنتج', 'الباركود'], true) || in_array($original, ['كود المنتج', 'الباركود'], true)) {
                $map['sku'] = $index;
            } elseif (in_array($cleanCol, ['quantity', 'qty', 'count', 'الكمية'], true) || in_array($original, ['الكمية'], true)) {
                $map['quantity'] = $index;
            } elseif (in_array($cleanCol, ['selling_price', 'price', 'سعر البيع', 'السعر'], true) || in_array($original, ['سعر البيع', 'السعر'], true)) {
                $map['selling_price'] = $index;
            } elseif (in_array($cleanCol, ['notes', 'ملاحظات'], true) || in_array($original, ['ملاحظات'], true)) {
                $map['notes'] = $index;
            }
        }

        $required = ['customer_name', 'customer_phone', 'governorate', 'city', 'address', 'sku', 'quantity', 'selling_price'];
        foreach ($required as $req) {
            if (! isset($map[$req])) {
                throw new DomainException("ملف CSV ينقصه عمود إلزامي: [{$req}]. يرجى التأكد من تطابق عناوين الأعمدة.");
            }
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $columns
     * @return array{
     *     customer_name: string,
     *     customer_phone: string,
     *     governorate_id: int,
     *     city: string,
     *     address: string,
     *     items: list<array{variant_id: int, quantity: int, selling_price_minor: int}>,
     *     notes?: string|null
     * }
     */
    protected function parseRow(array $row, array $columns, int $rowNumber): array
    {
        $name = trim((string) ($row[$columns['customer_name']] ?? ''));
        $phone = trim((string) ($row[$columns['customer_phone']] ?? ''));
        $govInput = trim((string) ($row[$columns['governorate']] ?? ''));
        $city = trim((string) ($row[$columns['city']] ?? ''));
        $address = trim((string) ($row[$columns['address']] ?? ''));
        $skuInput = trim((string) ($row[$columns['sku']] ?? ''));
        $qtyInput = trim((string) ($row[$columns['quantity']] ?? '1'));
        $priceInput = trim((string) ($row[$columns['selling_price']] ?? '0'));
        $notes = isset($columns['notes']) ? trim((string) ($row[$columns['notes']] ?? '')) : null;

        if ($name === '' || $phone === '' || $address === '' || $skuInput === '') {
            throw new DomainException("السطر {$rowNumber}: يحتوي على حقول فارغة مطلوبة (الاسم، الهاتف، العنوان، أو كود المنتج).");
        }

        // Resolve Governorate
        $governorateId = $this->resolveGovernorateId($govInput);

        // Resolve Variant
        $variant = ProductVariant::query()->where('sku', $skuInput)->first();
        if (! $variant) {
            if (is_numeric($skuInput)) {
                $variant = ProductVariant::query()->find((int) $skuInput);
            }
        }

        if (! $variant instanceof ProductVariant) {
            throw new DomainException("السطر {$rowNumber}: لم يتم العثور على منتج بكود SKU أو الرقم: [{$skuInput}].");
        }

        $quantity = max(1, (int) $qtyInput);
        $priceMinor = (int) round(((float) $priceInput) * 100);

        return [
            'customer_name' => $name,
            'customer_phone' => $phone,
            'governorate_id' => $governorateId,
            'city' => $city,
            'address' => $address,
            'notes' => $notes !== '' ? $notes : null,
            'items' => [
                [
                    'variant_id' => (int) $variant->id,
                    'quantity' => $quantity,
                    'selling_price_minor' => $priceMinor,
                ],
            ],
        ];
    }

    protected function resolveGovernorateId(string $input): int
    {
        if (is_numeric($input)) {
            $id = (int) $input;
            if (Governorate::where('id', $id)->exists()) {
                return $id;
            }
        }

        /** @var Governorate|null $gov */
        $gov = Governorate::query()
            ->where('name_ar', 'like', "%{$input}%")
            ->orWhere('name_en', 'like', "%{$input}%")
            ->first();

        if ($gov instanceof Governorate) {
            return (int) $gov->id;
        }

        // Fallback default (Cairo = 1)
        return 1;
    }
}
