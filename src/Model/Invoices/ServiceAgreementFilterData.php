<?php

namespace App\Model\Invoices;

use App\Entity\Worker;
use App\Enum\HostingProviderEnum;

class ServiceAgreementFilterData
{
    public ?string $project = null;
    public ?string $client = null;
    public ?bool $cybersecurityAgreement = null;
    public ?HostingProviderEnum $hostingProvider = null;
    public ?bool $active = null;
    public ?Worker $projectLead = null;
}
