<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class IssueDataCollection
{
    /** @var Collection<string, IssueData> */
    public Collection $issueData;
 

    public function __construct()
    {
        $this->issueData = new ArrayCollection();
    }
}
