<?php

namespace App\Model\Invoices;

class AccountData
{
    public readonly string $projectTrackerId;
    public readonly string $name;
    public readonly string $value;

    public function __construct(string $projectTrackerId, string $name, string $value)
    {
        $this->projectTrackerId = $projectTrackerId;
        $this->name = $name;
        $this->value = $value;
    }
}
