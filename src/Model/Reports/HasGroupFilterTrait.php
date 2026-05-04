<?php

namespace App\Model\Reports;

use App\Entity\WorkerGroup;

trait HasGroupFilterTrait
{
    public ?WorkerGroup $group = null;

    public function getGroup(): ?WorkerGroup
    {
        return $this->group;
    }

    public function setGroup(?WorkerGroup $group): void
    {
        $this->group = $group;
    }
}
