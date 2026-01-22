<?php

namespace App\Model\Reports;

final class CybersecurityWorklogData
{
    public function __construct(
        public int $id,
        public float $hours,
        public ?string $description,
        public ?string $worker,
    ) {
    }
}
