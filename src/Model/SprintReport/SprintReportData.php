<?php

namespace App\Model\SprintReport;

use Doctrine\Common\Collections\ArrayCollection;

class SprintReportData
{
    /** @var ArrayCollection<string, SprintReportTag> */
    public ArrayCollection $tags;
    /** @var ArrayCollection<string, SprintReportSprint> */
    public ArrayCollection $sprints;
    /** @var ArrayCollection<string, SprintReportIssue> */
    public ArrayCollection $issues;
    public float $spentSum;
    public float $spentHours;
    public float $remainingHours;
    public float $projectHours;
    public string $projectName;
    public string $milestoneName;
    public float $originalEstimateSum = 0.0;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->sprints = new ArrayCollection();
        $this->issues = new ArrayCollection();
    }
}
