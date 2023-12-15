<?php

namespace App\Model\Invoices;

class PagedResult
{
    public array $items = [];
    public int $startAt = 0;
    public int $maxResults = 0;
    public int $total = 0;

    public function __construct(array $items, int $startAt, int $maxResults, int $total)
    {
        $this->items = $items;
        $this->startAt = $startAt;
        $this->maxResults = $maxResults;
        $this->total = $total;
    }
}
