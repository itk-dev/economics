<?php

namespace App\Model\Planning;

use App\Entity\WorkerGroup;

class PlanningFormData
{
    public int $year;
    public ?WorkerGroup $group;
}
