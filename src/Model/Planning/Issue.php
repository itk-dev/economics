<?php

namespace App\Model\Planning;

class Issue
{
    public readonly string $key;
    public readonly string $displayName;
    public readonly ?float $remainingHours;
    public readonly ?string $link;
    public readonly string $sprintId;

    public function __construct(string $key, string $displayName, ?float $remainingHours, ?string $link, string $stringId)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->remainingHours = $remainingHours;
        $this->link = $link;
        $this->sprintId = $stringId;
    }
}
