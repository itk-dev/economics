<?php

namespace App\Interface;

use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectDataCollection;
use App\Model\Invoices\WorklogDataCollection;
use App\Model\Planning\PlanningData;

interface DataProviderInterface
{
    public function updateAll(bool $asyncJobQueue = false, bool $modified = false): void;

    public function update(string $className, bool $asyncJobQueue = false, bool $modified = false): void;
}
