<?php

namespace App\Interface;

interface DataProviderInterface
{
    /**
     * Update all data related to instances of the given DataProvider.
     *
     * @param bool                    $asyncJobQueue handle as asynchronous jobs
     * @param \DateTimeInterface|null $modifiedAfter only update elements modified after this date.
     */
    public function updateAll(bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void;

    /**
     * Update $className related to instances of the given DataProvider.
     *
     * @param string                  $className     the className of the entity to update
     * @param bool                    $asyncJobQueue handle as asynchronous jobs
     * @param \DateTimeInterface|null $modifiedAfter only update elements modified after this date.
     */
    public function update(string $className, bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void;
}
