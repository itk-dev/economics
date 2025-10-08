<?php

namespace App\Interface;

use App\Entity\DataProvider;

interface FetchDateInterface
{
    public function getOldestFetchTime(DataProvider $dataProvider, ?array $projectTrackerProjectIds = null);
}
