<?php

namespace App\Model\Invoices;

class PagedResult
{
    public function __construct(
        public readonly array $items,
        public readonly int $startAt,
        public readonly int $maxResults,
        public readonly int $total,
    ) {
    }
}
