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
    /**
     * Update all data related to instances of the given DataProvider.
     *
     * @param bool $asyncJobQueue Handle as asynchronous jobs.
     * @param bool $onlyModified Only fetch entries that have been modified since last update.
     * @return void
     */
    public function updateAll(bool $asyncJobQueue = false, bool $onlyModified = false): void;

    /**
     * Update $className related to instances of the given DataProvider.
     *
     * @param string $className The className of the entity to update.
     * @param bool $asyncJobQueue Handle as asynchronous jobs.
     * @param bool $onlyModified Only fetch entries that have been modified since last update.
     * @return void
     */
    public function update(string $className, bool $asyncJobQueue = false, bool $onlyModified = false): void;
}
