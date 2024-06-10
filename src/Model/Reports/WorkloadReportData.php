<?php

namespace App\Model\Reports;

use Doctrine\Common\Collections\ArrayCollection;

class WorkloadReportData
{
    public readonly string $id;
    public readonly string $viewmode;
    /** @var ArrayCollection<string, string> */
    public ArrayCollection $period;
    /** @var ArrayCollection<string, WorkloadReportWorker> */
    public ArrayCollection $workers;
    public int $currentPeriodNumeric;

    public function __construct(string $viewmode)
    {
        $this->viewmode = $viewmode;
        $this->period = new ArrayCollection();
        $this->workers = new ArrayCollection();
    }

    /**
     * Get current week.
     *
     * @return int $currentPeriodNumeric
     */
    public function getCurrentPeriodNumeric(): int
    {
        return $this->currentPeriodNumeric;
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
