<?php

namespace App\Model\Invoices;

use App\Entity\Version;
use DateTime;

class InvoiceEntryWorklogsFilterData
{
    public ?bool $isBilled = null;
    public ?DateTime $periodFrom = null;
    public ?DateTime $periodTo = null;
    public ?string $worker = null;
    public ?Version $version = null;
}
