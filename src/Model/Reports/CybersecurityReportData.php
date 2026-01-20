<?php

namespace App\Model\Reports;

final class CybersecurityReportData
{
    /**
     * @var array<string, CybersecurityProjectData>
     */
    public array $projects = [];

    public float $totalSpent = 0.0;
}
