<?php

namespace App\Service;

use App\Model\Planning\PlanningData;

interface DataProviderInterface
{
    public function getPlanningData(): PlanningData;
}
