<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ProjectDataCollection
{
    /** @var Collection<string, ProjectData> */
    public Collection $projectData;

    public function __construct()
    {
        $this->projectData = new ArrayCollection();
    }
}
