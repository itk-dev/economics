<?php

namespace App\Model\Invoices;

use App\Model\SprintReport\SprintReportVersions;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ProjectData
{
    public string $name;
    public string $projectTrackerId;
    public string $projectTrackerKey;
    public string $projectTrackerProjectUrl;
    /** @var Collection<string, SprintReportVersions> */
    public Collection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }
}
