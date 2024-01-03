<?php

namespace App\Service;

use App\Exception\ApiServiceException;
use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\IssueData;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\WorklogData;
use App\Model\Planning\PlanningData;
use App\Model\SprintReport\SprintReportData;
use App\Model\SprintReport\SprintReportProject;
use App\Model\SprintReport\SprintReportProjects;
use App\Model\SprintReport\SprintReportVersions;

interface ApiServiceInterface
{
    public function getEndpoints(): array;

    public function getProjectTrackerIdentifier(): string;

    /**
     * Get all accounts.
     *
     * @throws ApiServiceException
     */
    public function getAllAccounts(): mixed;

    /**
     * Get all accounts.
     *
     * @throws ApiServiceException
     */
    public function getAllCustomers(): mixed;

    /**
     * @throws ApiServiceException
     */
    public function getAllProjectCategories(): mixed;

    /**
     * Get all projects, including archived.
     *
     * @throws ApiServiceException
     */
    public function getAllProjects(): mixed;

    /**
     * Get current user permissions.
     *
     * @throws ApiServiceException
     */
    public function getCurrentUserPermissions(): mixed;

    /**
     * Get list of allowed permissions for current user.
     *
     * @throws ApiServiceException
     */
    public function getPermissionsList(): array;

    /**
     * Get project.
     *
     * @param $key
     *   A project key or id
     *
     * @throws ApiServiceException
     */
    public function getProject($key): mixed;

     /**
     * Create a jira project.
     *
     * See https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/#api/2/project-createProject
     *
     * @return ?string
     *
     * @throws ApiServiceException
     */
    public function createProject(array $data): ?string;

    /**
     * Create a jira customer.
     *
     * @throws ApiServiceException
     */
    public function createTimeTrackerCustomer(string $name, string $key): mixed;

    /**
     * Get tempo account base on key.
     *
     * @throws ApiServiceException
     */
    public function getTimeTrackerAccount(string $key): mixed;

     /**
     * Create a Jira account.
     *
     * @throws ApiServiceException
     */
    public function createTimeTrackerAccount(string $name, string $key, string $customerKey, string $contactUsername): mixed;

     /**
     * Create a project link to account.
     *
     * @param mixed $project
     *                       The project that was created on form submit
     * @param mixed $account
     *                       The account that was created on form submit
     *
     * @throws ApiServiceException
     */
    public function addProjectToTimeTrackerAccount(mixed $project, mixed $account): void;

    /**
     * Create project board.
     *
     * @throws ApiServiceException
     */
    public function createProjectBoard(string $type, mixed $project): void;

    /**
     * Get account based on id.
     *
     * @throws ApiServiceException
     */
    public function getAccount(string $accountId): mixed;

    /**
     * @throws ApiServiceException
     */
    public function getRateTableByAccount(string $accountId): mixed;

    /**
     * @throws ApiServiceException
     */
    public function getAccountIdsByProject(string $projectId): array;

    /**
     * Get all boards.
     *
     * @throws ApiServiceException
     */
    public function getAllBoards(): mixed;

    /**
     * Get all sprints for a given board.
     *
     * @param string $boardId board id
     * @param string $state sprint state. Defaults to future,active sprints.
     *
     * @throws ApiServiceException
     */
    public function getAllSprints(string $boardId): array;

    /**
     * Get all issues for given board and sprint.
     *
     * @param string $boardId id of the jira board to extract issues from
     * @param string $sprintId id of the sprint to extract issues for
     *
     * @return array array of issues
     *
     * @throws ApiServiceException
     */
    public function getIssuesInSprint(string $boardId, string $sprintId): array;

    /**
     * Create data for planning page.
     *
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getPlanningData(): PlanningData;

    /**
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getSprintReportData(string $projectId, string $versionId): SprintReportData;

    /**
     * @return array<ClientData>
     * @throws ApiServiceException
     */
    public function getClientDataForProject(string $projectId): array;

    /**
     * @return array<ProjectData>
     * @throws ApiServiceException
     */
    public function getAllProjectData(): array;

    /** @return array<WorklogData>
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getWorklogDataForProject(string $projectId): array;

    /** @return array<IssueData>
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getIssuesDataForProject(string $projectId): array;

    /**
     * @return array<AccountData>
     * @throws ApiServiceException
     */
    public function getAllAccountData(): array;
}
