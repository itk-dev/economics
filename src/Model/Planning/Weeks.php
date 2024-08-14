<?php

namespace App\Model\Planning;

use Doctrine\Common\Collections\ArrayCollection;

class Weeks
{
    /** @var ArrayCollection<0, int> */
    public ArrayCollection $weekCollection;
    public int $weeks;
    public float $weekGoalLow;
    public float $weekGoalHigh;
    public string $displayName;
    public ?bool $activeSprint;

    public function __construct()
    {
        $this->weekCollection = new ArrayCollection();
    }
}
