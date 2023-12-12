<?php

namespace App\Model\SprintReport;

use Doctrine\Common\Collections\ArrayCollection;

class SprintReportVersions
{
    /** @var ArrayCollection<string, SprintReportVersion> */
    public ArrayCollection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }
}
