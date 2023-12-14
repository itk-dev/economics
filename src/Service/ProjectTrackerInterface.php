<?php

namespace App\Service;

use App\Model\Planning\PlanningData;

interface ProjectTrackerInterface
{
    public function getPlanningData(): PlanningData;
}
