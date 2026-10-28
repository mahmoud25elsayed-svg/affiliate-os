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
        $rawCod = $data['reported_cod_minor'] ?? $data['cod_amount_minor'] ?? $data['cod'] ?? 0;
        $codMinor = is_numeric($rawCod) ? (int) (str_contains((string) $rawCod, '.') ? round((float) $rawCod * 100) : (int) $rawCod) : 0;

        $rawFee = $data['shipping_fee_minor'] ?? $data['shipping_fee'] ?? $data['fee'] ?? 0;
        $feeMinor = is_numeric($rawFee) ? (int) (str_contains((string) $rawFee, '.') ? round((float) $rawFee * 100) : (int) $rawFee) : 0;

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

