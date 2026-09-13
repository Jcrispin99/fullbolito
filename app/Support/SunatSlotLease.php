<?php

declare(strict_types=1);

namespace App\Support;

final readonly class SunatSlotLease
{
    public function __construct(
        public string $tenantId,
        public int $slotNumber,
        public int $saleId,
        public string $owner,
    ) {}
}
