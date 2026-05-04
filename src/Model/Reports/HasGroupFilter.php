<?php

namespace App\Model\Reports;

use App\Entity\WorkerGroup;

interface HasGroupFilter
{
    public function getGroup(): ?WorkerGroup;

    public function setGroup(?WorkerGroup $group): void;
}
