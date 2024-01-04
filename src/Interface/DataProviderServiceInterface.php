<?php

namespace App\Interface;

use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\WorklogData;
use App\Model\Planning\PlanningData;

interface DataProviderServiceInterface
{
    public function getPlanningData(): PlanningData;

    /**
     * @return array<ProjectData>
     */
    public function getAllProjectData(): array;

    /**
     * @return array<ClientData>
     */
    public function getClientDataForProject(string $projectId): array;

    /**
     * @return array<AccountData>
     */
    public function getAllAccountData(): array;

    public function getIssuesDataForProjectPaged(string $projectId, int $startAt = 0, $maxResults = 50): PagedResult;

    /** @return array<WorklogData> */
    public function getWorklogDataForProject(string $projectId): array;
}
