<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class WorkloadReportData
{
    public readonly string $id;
    /** The period type — 'week', 'month' or 'year'. */
    public readonly string $viewmode;
    public readonly int $year;
    public readonly WorkloadReportViewModeEnum $reportViewMode;
    /** @var ArrayCollection<string, string> */
    public ArrayCollection $period;
    /** @var ArrayCollection<string, WorkloadReportWorker> */
    public ArrayCollection $workers;
    public int $currentPeriodNumeric;
    public ArrayCollection $periodAverages;
    public float $totalAverage;

    public function __construct(string $viewmode, int $year, WorkloadReportViewModeEnum $reportViewMode)
    {
        $this->viewmode = $viewmode;
        $this->year = $year;
        $this->reportViewMode = $reportViewMode;
        $this->period = new ArrayCollection();
        $this->workers = new ArrayCollection();
        $this->periodAverages = new ArrayCollection();
        $this->totalAverage = 0;
    }

    /**
     * Set current week.
     */
    public function setCurrentPeriodNumeric(int $currentPeriodNumeric): self
    {
        $this->currentPeriodNumeric = $currentPeriodNumeric;

        return $this;
    }
}
