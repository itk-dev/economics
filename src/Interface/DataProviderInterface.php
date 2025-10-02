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
    public function update(bool $enableJobHandling = true): void;

    public function updateProjects(bool $enableJobHandling = true): void;
}
