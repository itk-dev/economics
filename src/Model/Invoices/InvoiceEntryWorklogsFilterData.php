<?php

namespace App\Model\Invoices;

use App\Entity\Version;
use DateTime;

class InvoiceEntryWorklogsFilterData
{
    public ?bool $isBilled;
    public ?DateTime $periodFrom;
    public ?DateTime $periodTo;
    public ?string $worker;
    public ?Version $version;
}
