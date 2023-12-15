<?php

namespace App\Model\Invoices;

class PagedResult
{
    public function __construct(
      public array $items,
      public int $startAt,
      public int $maxResults,
      public int $total
    )
    {
    }
}
