<?php

namespace App\Interface;

interface DataProviderInterface
{
    /**
     * Update all data related to instances of the given DataProvider.
     *
     * @param bool $asyncJobQueue handle as asynchronous jobs
     * @param bool $onlyModified only fetch entries that have been modified since last update
     *
     * @return void
     */
    public function updateAll(bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void;

    /**
     * Update $className related to instances of the given DataProvider.
     *
     * @param string $className the className of the entity to update
     * @param bool $asyncJobQueue handle as asynchronous jobs
     * @param bool $onlyModified only fetch entries that have been modified since last update
     *
     * @return void
     */
    public function update(string $className, bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void;
}
