<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

readonly class RemittanceImportRow
{
    public function __construct(
        public string $trackingNumber,
        public int $reportedCodMinor,
        public int $shippingFeeMinor = 0,
        public string $status = 'delivered',
        public ?string $notes = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $trackingNumber = trim((string) ($data['tracking_number'] ?? $data['trackingNumber'] ?? $data['awb'] ?? $data['waybill'] ?? ''));

        // AF-03: Deterministic unit resolution between minor units (_minor) and major EGP amounts
        if (isset($data['reported_cod_minor'])) {
            $codMinor = (int) $data['reported_cod_minor'];
        } elseif (isset($data['cod_amount_minor'])) {
            $codMinor = (int) $data['cod_amount_minor'];
        } elseif (isset($data['cod']) && is_numeric($data['cod'])) {
            $codMinor = (int) round(((float) $data['cod']) * 100);
        } else {
            $codMinor = 0;
        }

        if (isset($data['shipping_fee_minor'])) {
            $feeMinor = (int) $data['shipping_fee_minor'];
        } elseif (isset($data['shipping_fees_minor'])) {
            $feeMinor = (int) $data['shipping_fees_minor'];
        } elseif (isset($data['shipping_fee']) && is_numeric($data['shipping_fee'])) {
            $feeMinor = (int) round(((float) $data['shipping_fee']) * 100);
        } elseif (isset($data['fee']) && is_numeric($data['fee'])) {
            $feeMinor = (int) round(((float) $data['fee']) * 100);
        } else {
            $feeMinor = 0;
        }

        $status = (string) ($data['status'] ?? $data['reported_status'] ?? 'delivered');
        $notes = isset($data['notes']) ? (string) $data['notes'] : null;

        return new self(
            trackingNumber: $trackingNumber,
            reportedCodMinor: $codMinor,
            shippingFeeMinor: $feeMinor,
            status: $status,
            notes: $notes
        );
    }
}
