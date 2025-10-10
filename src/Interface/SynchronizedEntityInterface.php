<?php

namespace App\Interface;

use App\Entity\DataProvider;

interface SynchronizedEntityInterface
{
    public function getOldestFetchTime(DataProvider $dataProvider, ?array $projectTrackerProjectIds = null);
}
