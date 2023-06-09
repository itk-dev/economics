<?php

namespace App\Model\Invoices;

class InvoiceFilterData
{
    public ?bool $recorded = false;
    public ?string $createdBy = '';
    public ?bool $projectBilling = false;
}
