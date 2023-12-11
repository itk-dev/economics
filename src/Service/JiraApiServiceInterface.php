<?php

namespace App\Service;

use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\IssueData;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\WorklogData;
use App\Model\Planning\PlanningData;
use App\Model\SprintReport\SprintReportData;

interface JiraApiServiceInterface
{
    public function getEndpoints(): array;

    public function getProjectTrackerIdentifier(): string;

    public function getAllAccounts(): mixed;

    public function getAllCustomers(): mixed;

    public function getAllProjectCategories(): mixed;

    public function getAllProjects(): mixed;

    public function getCurrentUserPermissions(): mixed;

    public function getPermissionsList(): array;

    public function getProject($key): mixed;

    public function createProject(array $data): ?string;

    public function createTimeTrackerCustomer(string $name, string $key): mixed;

    public function getTimeTrackerAccount(string $key): mixed;

    public function createTimeTrackerAccount(string $name, string $key, string $customerKey, string $contactUsername): mixed;

    public function addProjectToTimeTrackerAccount(mixed $project, mixed $account): void;

    public function createProjectBoard(string $type, mixed $project): void;

    public function getAccount(string $accountId): mixed;

    public function getRateTableByAccount(string $accountId): mixed;

    /** @return array<string> */
    public function getAccountIdsByProject(string $projectId): array;

    public function getAllBoards(): mixed;

    public function getPlanningData(): PlanningData;

    public function getSprintReportData(string $projectId, string $versionId): SprintReportData;

    /**
     * @return array<ClientData>
     */
    public function getClientDataForProject(string $projectId): array;

    /**
     * @return array<ProjectData>
     */
    public function getAllProjectData(): array;

    /** @return array<WorklogData> */
    public function getWorklogDataForProject(string $projectId): array;

    /** @return array<IssueData> */
    public function getIssuesDataForProject(string $projectId): array;

    /**
     * @return array<AccountData>
     */
    public function getAllAccountData(): array;
}
