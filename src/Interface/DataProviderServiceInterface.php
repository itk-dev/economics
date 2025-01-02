<?php

namespace App\Interface;

use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectDataCollection;
use App\Model\Invoices\WorklogDataCollection;
use App\Model\Planning\PlanningData;
use App\Model\SprintReport\SprintReportData;
use App\Model\SprintReport\SprintReportProjects;
use App\Model\SprintReport\SprintReportVersions;

interface DataProviderServiceInterface
{
    public function getPlanningDataWeeks(): PlanningData;

    /**
     * @return array<ClientData>
     */
    public function getClientDataForProject(string $projectId): array;

    /**
     * @return array<AccountData>
     */
    public function getAllAccountData(): array;

    public function getIssuesDataForProjectPaged(string $projectId, int $startAt = 0, $maxResults = 50): PagedResult;

    public function getSprintReportData(string $projectId, string $versionId): SprintReportData;

    public function getSprintReportProjects(): SprintReportProjects;

    public function getSprintReportVersions(string $projectId): SprintReportVersions;

    public function getProjectDataCollection(): ProjectDataCollection;

    public function getWorklogDataCollection(string $projectId): WorklogDataCollection;
}
