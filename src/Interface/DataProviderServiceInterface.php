<?php

namespace App\Interface;

use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectDataCollection;
use App\Model\Invoices\WorklogDataCollection;

interface DataProviderServiceInterface
{
    /**
     * @return array<ClientData>
     */
    public function getClientDataForProject(string $projectId): array;

    /**
     * @return array<AccountData>
     */
    public function getAllAccountData(): array;

    public function getIssuesDataForProjectPaged(string $projectId, int $startAt = 0, $maxResults = 50): PagedResult;

    public function getProjectDataCollection(): ProjectDataCollection;

    public function getWorklogDataCollection(string $projectId): WorklogDataCollection;
}
