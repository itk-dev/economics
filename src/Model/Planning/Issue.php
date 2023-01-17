<?php

namespace App\Model\Planning;

class Issue
{
    public string $key;
    public string $displayName;
    public ?float $remainingHours;
    public string $link;
    public string $sprintId;

    /**
     * @param string $key
     * @param string $displayName
     * @param float|null $remainingHours
     * @param string $link
     * @param string $stringId
     */
    public function __construct(string $key, string $displayName, ?float $remainingHours, string $link, string $stringId)
    {
        $this->key = $key;
        $this->displayName = $displayName;
        $this->remainingHours = $remainingHours;
        $this->link = $link;
        $this->sprintId = $stringId;
    }
}
