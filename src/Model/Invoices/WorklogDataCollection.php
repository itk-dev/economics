<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class WorklogDataCollection
{
    /** @var Collection<string, WorklogData> */
    public Collection $worklogData;
 

    public function __construct()
    {
        $this->worklogData = new ArrayCollection();
    }
}
