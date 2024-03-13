<?php

namespace App\Model\Invoices;

use App\Entity\Project;

class IssueFilterData
{
    public ?string $name = null;
    public ?Project $project = null;
}
