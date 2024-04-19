<?php

namespace App\Model\Invoices;

class InvoiceFilterData
{
    public ?string $query = '';
    public ?bool $recorded = false;
    public ?bool $noCost = null;
    public ?string $createdBy = '';
    public ?bool $projectBilling = false;
}
