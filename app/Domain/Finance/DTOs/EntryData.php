<?php

declare(strict_types=1);

namespace App\Domain\Finance\DTOs;

readonly class EntryData
{
    /**
     * @param  list<LedgerLineData>  $lines
     */
    public function __construct(
        public string $type,
        public string $idempotencyKey,
        public string $description,
        public array $lines,
        public ?string $refType = null,
        public ?int $refId = null,
        public ?int $createdBy = null
    ) {}
}
