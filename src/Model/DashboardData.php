<?php

namespace App\Model;

class DashboardData
{
    public function __construct(
        public readonly float $workHours,
        public readonly int $year,
        public readonly float $norm,
        public readonly array $monthStatuses,
        public readonly array $weekStatuses,
    ) {
    }
}
