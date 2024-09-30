<?php

namespace App\Model\Invoices;

class ProjectFilterData
{
    public ?string $name = null;
    public ?string $key = null;
    public ?bool $include = true;
    public ?bool $isBillable = null;
}
