<?php

namespace App\Model\Invoices;

class AccountData
{
    public readonly string $projectTrackerId;
    public readonly string $name;
    public readonly string $value;
    public readonly string $category;
    public readonly string $status;

    public function __construct(string $projectTrackerId, string $name, string $value, string $category, string $status)
    {
        $this->projectTrackerId = $projectTrackerId;
        $this->name = $name;
        $this->value = $value;
        $this->category = $category;
        $this->status = $status;
    }
}
