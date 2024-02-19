<?php

namespace App\Model\Invoices;

use App\Enum\ClientTypeEnum;

class ClientData
{
    public string $projectTrackerId;
    public string $name;
    public ?string $contact = null;
    public ?string $customerKey = null;
    public ?ClientTypeEnum $type = null;
    public ?float $standardPrice = null;
    public ?string $psp = null;
    public ?string $ean = null;
}
