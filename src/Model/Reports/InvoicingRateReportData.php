<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class InvoicingRateReportData
{
    public readonly string $id;
    public readonly string $viewmode;
    /** @var ArrayCollection<string, string> */
    public ArrayCollection $period;
    /** @var ArrayCollection<string, InvoicingRateReportWorker> */
    public ArrayCollection $workers;
    public int $currentPeriodNumeric;
    public ArrayCollection $periodAverages;
    public float $totalAverage;
    /**
     * @var false
     */
    public bool $includeIssues;

    public function __construct(string $viewmode)
    {
        $this->viewmode = $viewmode;
        $this->period = new ArrayCollection();
        $this->workers = new ArrayCollection();
        $this->periodAverages = new ArrayCollection();
        $this->includeIssues = false;
        $this->totalAverage = 0;
    }

    /**
     * Set current week.
     *
     * @param int $currentPeriodNumeric
     *
     * @return self
     */
    public function setCurrentPeriodNumeric(int $currentPeriodNumeric): self
    {
        $this->currentPeriodNumeric = $currentPeriodNumeric;

        return $this;
    }
}
