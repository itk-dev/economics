<?php

namespace App\Model\Invoices;

class InvoiceFilterData
{
    public ?string $query = '';
    public ?bool $recorded = false;
    public ?string $createdBy = '';
    public ?bool $projectBilling = false;
}
