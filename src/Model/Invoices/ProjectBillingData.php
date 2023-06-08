<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ProjectBillingData
{
    /** @var Collection<string, ProjectBillingInvoiceData> */
    public Collection $invoices;

    public function __construct()
    {
        $this->invoices = new ArrayCollection();
    }
}
