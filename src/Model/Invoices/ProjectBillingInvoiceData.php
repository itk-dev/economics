<?php

namespace App\Model\Invoices;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ProjectBillingInvoiceData
{
    /** @var Collection<string, ProjectBillingIssueData> */
    public Collection $issues;
    public readonly AccountData $account;

    public function __construct(AccountData $account)
    {
        $this->account = $account;
        $this->issues = new ArrayCollection();
    }
}
