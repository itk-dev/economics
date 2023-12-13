<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ProjectData
{
    public string $name;
    public string $projectTrackerId;
    public string $projectTrackerKey;
    public string $projectTrackerProjectUrl;
    /** @var Collection<string, VersionData> */
    public Collection $versions;

    public function __construct(string $name, string $projectTrackerId, string $projectTrackerKey, string $projectTrackerProjectUrl, Collection $versions)
    {
        $this->name = $name;
        $this->projectTrackerId = $projectTrackerId;
        $this->projectTrackerKey = $projectTrackerKey;
        $this->projectTrackerProjectUrl = $projectTrackerProjectUrl;
        $this->versions = $versions;
    }
}
