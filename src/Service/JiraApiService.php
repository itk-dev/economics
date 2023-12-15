<?php

namespace App\Service;

use App\Enum\ClientTypeEnum;
use App\Exception\ApiServiceException;
use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\IssueData;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\VersionData;
use App\Model\Invoices\WorklogData;
use App\Model\Planning\Assignee;
use App\Model\Planning\AssigneeProject;
use App\Model\Planning\Issue;
use App\Model\Planning\PlanningData;
use App\Model\Planning\Project;
use App\Model\Planning\Sprint;
use App\Model\Planning\SprintSum;
use App\Model\SprintReport\SprintReportData;
use App\Model\SprintReport\SprintReportEpic;
use App\Model\SprintReport\SprintReportIssue;
use App\Model\SprintReport\SprintReportSprint;
use App\Model\SprintReport\SprintStateEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JiraApiService implements ApiServiceInterface, ProjectTrackerInterface
{
    private const PROJECT_TRACKER_IDENTIFIER = 'JIRA';
    private const CPB_ACCOUNT_MANAGER = 'anbjv';
    private const NO_SPRINT = 'NoSprint';
    private const API_PATH_SEARCH = '/rest/api/2/search';
    private const API_PATH_VERSION = '/rest/api/2/version/';
    private const API_PATH_PROJECT_CATEGORIES = '/rest/api/2/projectCategory';
    private const API_PATH_ACCOUNT = '/rest/tempo-accounts/1/account/';
    private const API_PATH_ACCOUNT_BY_KEY = '/rest/tempo-accounts/1/account/key/';
    private const API_PATH_CUSTOMERS = '/rest/tempo-accounts/1/customer/';
    private const API_PATH_PROJECT = '/rest/api/2/project';
    private const API_PATH_PROJECT_BY_ID = '/rest/api/2/project/';
    private const API_PATH_MY_PERMISSIONS = '/rest/api/2/mypermissions';
    private const API_PATH_LINK_PROJECT_TO_ACCOUNT = '/rest/tempo-accounts/1/link/';
    private const API_PATH_FILTER = '/rest/api/2/filter';
    private const API_PATH_BOARD = '/rest/agile/1.0/board';
    private const API_PATH_EPIC = '/rest/agile/1.0/epic';
    private const API_PATH_RATE_TABLE = '/rest/tempo-accounts/1/ratetable';
    private const API_PATH_ACCOUNT_IDS_BY_PROJECT = '/rest/tempo-accounts/1/link/project/';

    public function __construct(
        protected readonly HttpClientInterface $jiraProjectTrackerApi,
        protected readonly array $customFieldMappings,
        protected readonly string $defaultBoard,
        protected readonly string $jiraUrl,
        protected readonly float $weekGoalLow,
        protected readonly float $weekGoalHigh,
        protected readonly string $sprintNameRegex,
    ) {
    }

    public function getEndpoints(): array
    {
        return [
            'base' => $this->jiraUrl,
        ];
    }

    public function getProjectTrackerIdentifier(): string
    {
        return self::PROJECT_TRACKER_IDENTIFIER;
    }

    /**
     * @throws ApiServiceException
     */
    public function getAllProjectCategories(): mixed
    {
        return $this->get(self::API_PATH_PROJECT_CATEGORIES);
    }

    /**
     * Get all accounts.
     *
     * @throws ApiServiceException
     */
    public function getAllAccounts(): mixed
    {
        return $this->get(self::API_PATH_ACCOUNT);
    }

    /**
     * Get all accounts.
     *
     * @throws ApiServiceException
     */
    public function getAllCustomers(): mixed
    {
        return $this->get(self::API_PATH_CUSTOMERS);
    }

    /**
     * Get all projects, including archived.
     *
     * @throws ApiServiceException
     */
    public function getAllProjects(): mixed
    {
        return $this->get(self::API_PATH_PROJECT);
    }

    /**
     * Get project.
     *
     * @param $key
     *   A project key or id
     *
     * @throws ApiServiceException
     */
    public function getProject($key): mixed
    {
        return $this->get(self::API_PATH_PROJECT_BY_ID.$key);
    }

    /**
     * Get current user permissions.
     *
     * @throws ApiServiceException
     */
    public function getCurrentUserPermissions(): mixed
    {
        return $this->get(self::API_PATH_MY_PERMISSIONS);
    }

    /**
     * Get list of allowed permissions for current user.
     *
     * @throws ApiServiceException
     */
    public function getPermissionsList(): array
    {
        $list = [];
        $restPermissions = $this->getCurrentUserPermissions();
        if (isset($restPermissions->permissions) && \is_object($restPermissions->permissions)) {
            foreach ($restPermissions->permissions as $permission_name => $value) {
                if (isset($value->havePermission) && true === $value->havePermission) {
                    $list[] = $permission_name;
                }
            }
        }

        return $list;
    }

    /**
     * Create a jira project.
     *
     * See https://docs.atlassian.com/software/jira/docs/api/REST/9.3.0/#api/2/project-createProject
     *
     * @return ?string
     *
     * @throws ApiServiceException
     */
    public function createProject(array $data): ?string
    {
        $projectKey = strtoupper($data['form']['project_key']);
        $project = [
            'key' => $projectKey,
            'name' => $data['form']['project_name'],
            'projectTypeKey' => 'software',
            'projectTemplateKey' => 'com.pyxis.greenhopper.jira:basic-software-development-template',
            'description' => $data['form']['description'],
            'lead' => $data['selectedTeamConfig']['team_lead'],
            'assigneeType' => 'UNASSIGNED',
            'avatarId' => 10324, // Default avatar image
            'permissionScheme' => $data['selectedTeamConfig']['permission_scheme'],
            'notificationScheme' => 10000, // Default Notification Scheme
            'workflowSchemeId' => $data['selectedTeamConfig']['workflow_scheme'],
            'categoryId' => $data['selectedTeamConfig']['project_category'],
        ];

        $response = $this->post(self::API_PATH_PROJECT, $project);

        return $response->key == $projectKey ? $projectKey : null;
    }

    /**
     * Create a jira customer.
     *
     * @throws ApiServiceException
     */
    public function createTimeTrackerCustomer(string $name, string $key): mixed
    {
        return $this->post(self::API_PATH_CUSTOMERS,
            [
                'isNew' => 1,
                'name' => $name,
                'key' => $key,
            ]
        );
    }

    /**
     * Create a Jira account.
     *
     * @throws ApiServiceException
     */
    public function createTimeTrackerAccount(string $name, string $key, string $customerKey, string $contactUsername): mixed
    {
        return $this->post(self::API_PATH_ACCOUNT,
            [
                'name' => $name,
                'key' => $key,
                'status' => 'OPEN',
                'category' => [
                    'key' => 'DRIFT',
                ],
                'customer' => [
                    'key' => $customerKey,
                ],
                'contact' => [
                    'username' => $contactUsername,
                ],
                'lead' => [
                    'username' => $this::CPB_ACCOUNT_MANAGER,
                ],
            ]
        );
    }

    /**
     * Get tempo account base on key.
     *
     * @throws ApiServiceException
     */
    public function getTimeTrackerAccount(string $key): mixed
    {
        return $this->get(self::API_PATH_ACCOUNT_BY_KEY.$key);
    }

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
    public function addProjectToTimeTrackerAccount(mixed $project, mixed $account): void
    {
        $this->post(self::API_PATH_LINK_PROJECT_TO_ACCOUNT, [
            'scopeType' => 'PROJECT',
            'defaultAccount' => 'true',
            'linkType' => 'MANUAL',
            'key' => $project->key,
            'accountId' => $account->id,
            'scope' => $project->id,
        ]);
    }

    /**
     * Create project board.
     *
     * @throws ApiServiceException
     */
    public function createProjectBoard(string $type, mixed $project): void
    {
        // If no template is configured don't create a board.
        if (empty($this->formData['selectedTeamConfig']['board_template'])) {
            return;
        }

        // Create project filter.
        $filterResponse = $this->post(self::API_PATH_FILTER, [
            'name' => 'Filter for Project: '.$project->name,
            'description' => 'Project filter for '.$project->name,
            'jql' => 'project = '.$project->key.' ORDER BY Rank ASC',
            'favourite' => false,
            'editable' => false,
        ]);

        // Share project filter with project members.
        $this->post(self::API_PATH_FILTER.'/'.$filterResponse->id.'/permission', [
            'type' => 'project',
            'projectId' => $project->id,
            'view' => true,
            'edit' => false,
        ]);

        // Create board with project filter.
        $this->post(self::API_PATH_BOARD, [
            'name' => 'Project: '.$project->name,
            'type' => $type,
            'filterId' => $filterResponse->id,
        ]);
    }

    /**
     * Get account based on id.
     *
     * @throws ApiServiceException
     */
    public function getAccount(string $accountId): mixed
    {
        return $this->get(self::API_PATH_ACCOUNT.$accountId.'/');
    }

    /**
     * @throws ApiServiceException
     */
    public function getRateTableByAccount(string $accountId): mixed
    {
        return $this->get(self::API_PATH_RATE_TABLE, [
            'scopeId' => $accountId,
            'scopeType' => 'ACCOUNT',
        ]);
    }

    /**
     * @throws ApiServiceException
     */
    public function getAccountIdsByProject(string $projectId): array
    {
        $projectLinks = $this->get(self::API_PATH_ACCOUNT_IDS_BY_PROJECT.$projectId);

        return array_reduce($projectLinks, function ($carry, $item) {
            $carry[] = (string) $item->accountId;

            return $carry;
        }, []);
    }

    /**
     * Get all boards.
     *
     * @throws ApiServiceException
     */
    public function getAllBoards(): mixed
    {
        return $this->get(self::API_PATH_BOARD);
    }

    /**
     * Get all sprints for a given board.
     *
     * @param string $boardId board id
     * @param string $state sprint state. Defaults to future,active sprints.
     *
     * @throws ApiServiceException
     */
    public function getAllSprints(string $boardId, string $state = 'future,active'): array
    {
        $sprints = [];

        $startAt = 0;
        while (true) {
            $result = $this->get(self::API_PATH_BOARD.'/'.$boardId.'/sprint', [
                'startAt' => $startAt,
                'maxResults' => 50,
                'state' => $state,
            ]);
            $sprints = array_merge($sprints, $result->values);

            if ($result->isLast) {
                break;
            }

            $startAt += 50;
        }

        return $sprints;
    }

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
    public function getIssuesInSprint(string $boardId, string $sprintId): array
    {
        $issues = [];
        $fields = implode(
            ',',
            [
                'timetracking',
                'summary',
                'status',
                'assignee',
                'project',
            ]
        );

        $startAt = 0;
        while (true) {
            $result = $this->get(self::API_PATH_BOARD.'/'.$boardId.'/sprint/'.$sprintId.'/issue', [
                'startAt' => $startAt,
                'fields' => $fields,
            ]);
            $issues = array_merge($issues, $result->issues);

            $startAt += 50;

            if ($startAt > $result->total) {
                break;
            }
        }

        return $issues;
    }

    /**
     * Create data for planning page.
     *
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getPlanningData(): PlanningData
    {
        $planning = new PlanningData();
        $assignees = $planning->assignees;
        $projects = $planning->projects;
        $sprints = $planning->sprints;

        $boardId = $this->defaultBoard;

        $sprintIssues = [];

        $allSprints = $this->getAllSprints($boardId);

        foreach ($allSprints as $sprintData) {
            // Expected sprint name examples:
            //   DEV sprint uge 2-3-4.23
            //   ServiceSupport uge 5.23
            // From this we extract the number of weeks the sprint covers.
            // This is used to calculate the sprint goals low and high points.
            $pattern = !empty($this->sprintNameRegex) ? $this->sprintNameRegex : "/(?<weeks>(?:-?\d+-?)*)\.(?<year>\d+)$/";

            $matches = [];

            preg_match_all($pattern, $sprintData->name, $matches);

            if (!empty($matches['weeks'])) {
                $weeks = count(explode('-', $matches['weeks'][0]));
            } else {
                $weeks = 1;
            }

            $sprint = new Sprint(
                $sprintData->id,
                $weeks,
                $this->weekGoalLow * $weeks,
                $this->weekGoalHigh * $weeks,
                $sprintData->name,
            );
            $sprints->add($sprint);

            $issues = $this->getIssuesInSprint($boardId, $sprintData->id);

            $sprintIssues[$sprintData->id] = $issues;
        }

        foreach ($sprintIssues as $sprintId => $issues) {
            foreach ($issues as $issueData) {
                if ('done' !== $issueData->fields->status->statusCategory->key) {
                    $projectData = $issueData->fields->project;
                    $projectKey = (string) $projectData->key;
                    $projectDisplayName = $projectData->name;
                    $remainingSeconds = $issueData->fields->timetracking->remainingEstimateSeconds ?? 0;

                    if (empty($issueData->fields->assignee)) {
                        $assigneeKey = 'unassigned';
                        $assigneeDisplayName = 'Unassigned';
                    } else {
                        $assigneeKey = (string) $issueData->fields->assignee->key;
                        $assigneeDisplayName = $issueData->fields->assignee->displayName;
                    }

                    // Add assignee if not already added.
                    if (!$assignees->containsKey($assigneeKey)) {
                        $assignees->set($assigneeKey, new Assignee($assigneeKey, $assigneeDisplayName));
                    }

                    /** @var Assignee $assignee */
                    $assignee = $assignees->get($assigneeKey);

                    // Add sprint if not already added.
                    if (!$assignee->sprintSums->containsKey($sprintId)) {
                        $assignee->sprintSums->set($sprintId, new SprintSum($sprintId));
                    }

                    /** @var SprintSum $sprintSum */
                    $sprintSum = $assignee->sprintSums->get($sprintId);
                    $sprintSum->sumSeconds += $remainingSeconds;
                    $sprintSum->sumHours = $sprintSum->sumSeconds / (60 * 60);

                    // Add assignee project if not already added.
                    if (!$assignee->projects->containsKey($projectKey)) {
                        $assigneeProject = new AssigneeProject($projectKey, $projectDisplayName);
                        $assignee->projects->set($projectKey, $assigneeProject);
                    }

                    /** @var AssigneeProject $assigneeProject */
                    $assigneeProject = $assignee->projects->get($projectKey);

                    // Add project sprint sum if not already added.
                    if (!$assigneeProject->sprintSums->containsKey($sprintId)) {
                        $assigneeProject->sprintSums->set($sprintId, new SprintSum($sprintId));
                    }

                    /** @var SprintSum $projectSprintSum */
                    $projectSprintSum = $assigneeProject->sprintSums->get($sprintId);
                    if (isset($projectSprintSum)) {
                        $projectSprintSum->sumSeconds += $remainingSeconds;
                        $projectSprintSum->sumHours = $projectSprintSum->sumSeconds / (60 * 60);
                    }

                    $assigneeProject->issues->add(
                        new Issue(
                            $issueData->key,
                            $issueData->fields->summary,
                            isset($issueData->fields->timetracking->remainingEstimateSeconds) ? $remainingSeconds / (60 * 60) : null,
                            $this->jiraUrl.'/browse/'.$issueData->key,
                            $sprintId
                        )
                    );

                    // Add project if not already added.
                    if (!$projects->containsKey($projectKey)) {
                        $projects->set($projectKey, new Project(
                            $projectKey,
                            $projectDisplayName,
                        ));
                    }

                    /** @var Project $project */
                    $project = $projects->get($projectKey);

                    // Add sprint sum if not already added.
                    if (!$project->sprintSums->containsKey($sprintId)) {
                        $project->sprintSums->set($sprintId, new SprintSum($sprintId));
                    }

                    /** @var SprintSum $projectSprintSum */
                    $projectSprintSum = $project->sprintSums->get($sprintId);
                    $projectSprintSum->sumSeconds += $remainingSeconds;
                    $projectSprintSum->sumHours = $projectSprintSum->sumSeconds / (60 * 60);

                    if (!$project->assignees->containsKey($assigneeKey)) {
                        $project->assignees->set($assigneeKey, new AssigneeProject(
                            $assigneeKey,
                            $assigneeDisplayName,
                        ));
                    }

                    /** @var AssigneeProject $projectAssignee */
                    $projectAssignee = $project->assignees->get($assigneeKey);

                    if (!$projectAssignee->sprintSums->containsKey($sprintId)) {
                        $projectAssignee->sprintSums->set($sprintId, new SprintSum($sprintId));
                    }

                    /** @var SprintSum $projectAssigneeSprintSum */
                    $projectAssigneeSprintSum = $projectAssignee->sprintSums->get($sprintId);
                    $projectAssigneeSprintSum->sumSeconds += $remainingSeconds;
                    $projectAssigneeSprintSum->sumHours = $projectAssigneeSprintSum->sumSeconds / (60 * 60);

                    $projectAssignee->issues->add(new Issue(
                        $issueData->key,
                        $issueData->fields->summary,
                        isset($issueData->fields->timetracking->remainingEstimateSeconds) ? $remainingSeconds / (60 * 60) : null,
                        $this->jiraUrl.'/browse/'.$issueData->key,
                        $sprintId
                    ));
                }
            }
        }

        // Sort assignees by name.
        /** @var \ArrayIterator $iterator */
        $iterator = $assignees->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->displayName) <=> mb_strtolower($b->displayName);
        });
        $planning->assignees = new ArrayCollection(iterator_to_array($iterator));

        // Sort projects by name.
        /** @var \ArrayIterator $iterator */
        $iterator = $projects->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->displayName) <=> mb_strtolower($b->displayName);
        });
        $planning->projects = new ArrayCollection(iterator_to_array($iterator));

        return $planning;
    }

    /**
     * Get custom field id by field name.
     *
     * These refer to mappings set in jira_economics.local.yaml.
     */
    private function getCustomFieldId(string $fieldName): bool|string
    {
        return isset($this->customFieldMappings[$fieldName]) ? 'customfield_'.$this->customFieldMappings[$fieldName] : false;
    }

    /**
     * @throws ApiServiceException
     */
    private function getIssuesForProjectVersion($projectId, $versionId): array
    {
        $issues = [];

        // Get customFields from Jira.
        $customFieldEpicLinkId = $this->getCustomFieldId('Epic Link');
        $customFieldSprintId = $this->getCustomFieldId('Sprint');

        // Get all issues for version.
        $fields = implode(
            ',',
            [
                'timetracking',
                'worklog',
                'timespent',
                'timeoriginalestimate',
                'summary',
                'assignee',
                'status',
                'resolutionDate',
                $customFieldEpicLinkId,
                $customFieldSprintId,
            ]
        );

        $startAt = 0;

        // Get issues for the given project and version.
        do {
            $results = $this->get(
                self::API_PATH_SEARCH,
                [
                    'jql' => 'fixVersion='.$versionId,
                    'project' => $projectId,
                    'maxResults' => 50,
                    'fields' => $fields,
                    'startAt' => $startAt,
                ]
            );

            $issues = array_merge($issues, $results->issues);

            $startAt += 50;
        } while (isset($results->total) && $results->total > $startAt);

        return $issues;
    }

    /**
     * @throws ApiServiceException
     */
    private function getIssueSprint($issueEntry): SprintReportSprint
    {
        $customFieldSprintId = $this->getCustomFieldId('Sprint');

        // Get sprints for issue.
        if (isset($issueEntry->fields->{$customFieldSprintId})) {
            foreach ($issueEntry->fields->{$customFieldSprintId} as $sprintString) {
                // Remove everything before and after brackets.
                $replace = preg_replace(
                    ['/.*\[/', '/].*/'],
                    '',
                    (string) $sprintString
                );
                $fields = explode(',', $replace);

                $sprint = [];

                foreach ($fields as $field) {
                    $split = explode('=', $field);

                    if (count($split) > 1) {
                        $value = '<null>' == $split[1] ? null : $split[1];

                        $sprint[$split[0]] = $value;
                    }
                }

                if (!isset($sprint['id']) || !isset($sprint['name']) || !isset($sprint['state'])) {
                    continue;
                }

                $sprintState = SprintStateEnum::OTHER;

                switch ($sprint['state']) {
                    case 'ACTIVE':
                        $sprintState = SprintStateEnum::ACTIVE;
                        break;
                    case 'FUTURE':
                        $sprintState = SprintStateEnum::FUTURE;
                        break;
                }

                return new SprintReportSprint(
                    $sprint['id'],
                    $sprint['name'],
                    $sprintState,
                    $sprint['startDate'] ? strtotime($sprint['startDate']) : null,
                    $sprint['endDate'] ? strtotime($sprint['endDate']) : null,
                    $sprint['completeDate'] ? strtotime($sprint['completeDate']) : null,
                );
            }
        }

        throw new ApiServiceException('Sprint not found', 404);
    }

    /**
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getSprintReportData(string $projectId, string $versionId): SprintReportData
    {
        $sprintReportData = new SprintReportData();
        $epics = $sprintReportData->epics;
        $issues = $sprintReportData->issues;
        $sprints = $sprintReportData->sprints;

        $spentSum = 0;
        $remainingSum = 0;

        $epics->set('NoEpic', new SprintReportEpic('NoEpic', 'Uden Epic'));

        // Get version and project.
        $version = $this->get(self::API_PATH_VERSION.$versionId);
        $project = $this->getProject($projectId);

        // Get customField for Jira.
        $customFieldEpicLinkId = $this->getCustomFieldId('Epic Link');

        $issueEntries = $this->getIssuesForProjectVersion($projectId, $versionId);

        foreach ($issueEntries as $issueEntry) {
            $issue = new SprintReportIssue();
            $issues->add($issue);

            // Set issue epic.
            if (isset($issueEntry->fields->{$customFieldEpicLinkId})) {
                $epicLinkId = (string) $issueEntry->fields->{$customFieldEpicLinkId};

                // Add to epics if not already added.
                if (!$epics->containsKey($epicLinkId)) {
                    $epicData = $this->get(self::API_PATH_EPIC.'/'.$epicLinkId);

                    $epic = new SprintReportEpic($epicLinkId, $epicData->name);
                    $epics->set($epicLinkId, $epic);
                } else {
                    $epic = $epics->get($issueEntry->fields->{$customFieldEpicLinkId});
                }
            } else {
                $epic = $epics->get('NoEpic');
            }

            if (!$epic instanceof SprintReportEpic) {
                continue;
            }

            $issue->epic = $epic;

            // Get sprint for issue.
            try {
                $issueSprint = $this->getIssueSprint($issueEntry);

                if (!$sprints->containsKey($issueSprint->id)) {
                    $sprints->set($issueSprint->id, $issueSprint);
                }

                // Set which sprint the issue is assigned to.
                if (SprintStateEnum::ACTIVE === $issueSprint->state || SprintStateEnum::FUTURE === $issueSprint->state) {
                    $issue->assignedToSprint = $issueSprint;
                }
            } catch (ApiServiceException) {
                // Ignore if sprint is not found.
            }

            foreach ($issueEntry->fields->worklog->worklogs as $worklogData) {
                $workLogStarted = strtotime($worklogData->started);

                $worklogSprints = array_filter($sprints->toArray(), function ($sprintEntry) use ($workLogStarted) {
                    /* @var SprintReportSprint $sprintEntry */
                    return
                        $sprintEntry->startDateTimestamp <= $workLogStarted
                        && ($sprintEntry->completedDateTimstamp ?? $sprintEntry->endDateTimestamp) > $workLogStarted;
                });

                $worklogSprintId = self::NO_SPRINT;

                if (!empty($worklogSprints)) {
                    $worklogSprint = array_pop($worklogSprints);

                    $worklogSprintId = $worklogSprint->id;
                }

                $newLoggedWork = (float) ($issue->epic->loggedWork->containsKey($worklogSprintId) ? $issue->epic->loggedWork->get($worklogSprintId) : 0) + $worklogData->timeSpentSeconds;
                $issue->epic->loggedWork->set($worklogSprintId, $newLoggedWork);
            }

            // Accumulate spentSum.
            $spentSum += $issueEntry->fields->timespent;
            $issue->epic->spentSum += $issueEntry->fields->timespent;

            // Accumulate remainingSum.
            if ('Done' !== $issueEntry->fields->status->name && isset($issueEntry->fields->timetracking->remainingEstimateSeconds)) {
                $remainingEstimateSeconds = $issueEntry->fields->timetracking->remainingEstimateSeconds;
                $remainingSum += $remainingEstimateSeconds;

                $issue->epic->remainingSum += $remainingEstimateSeconds;

                if (!empty($issue->assignedToSprint)) {
                    $assignedToSprint = $issue->assignedToSprint;
                    $newRemainingWork = (float) ($issue->epic->remainingWork->containsKey($assignedToSprint->id) ? $issue->epic->remainingWork->get($assignedToSprint->id) : 0) + $remainingEstimateSeconds;
                    $issue->epic->remainingWork->set($assignedToSprint->id, $newRemainingWork);
                    $issue->epic->plannedWorkSum += $remainingEstimateSeconds;
                }
            }

            // Accumulate originalEstimateSum.
            if (isset($issueEntry->fields->timeoriginalestimate)) {
                $issue->epic->originalEstimateSum += $issueEntry->fields->timeoriginalestimate;

                $sprintReportData->originalEstimateSum += $issueEntry->fields->timeoriginalestimate;
            }
        }

        // Sort sprints by key.
        /** @var \ArrayIterator $iterator */
        $iterator = $sprints->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->id) <=> mb_strtolower($b->id);
        });
        $sprints = new ArrayCollection(iterator_to_array($iterator));

        // Sort epics by name.
        /** @var \ArrayIterator $iterator */
        $iterator = $epics->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->name) <=> mb_strtolower($b->name);
        });
        $epics = new ArrayCollection(iterator_to_array($iterator));

        // Calculate spent, remaining hours.
        $spentHours = $spentSum / 3600;
        $remainingHours = $remainingSum / 3600;

        $sprintReportData->projectName = $project->name;
        $sprintReportData->versionName = $version->name;
        $sprintReportData->remainingHours = $remainingHours;
        $sprintReportData->spentHours = $spentHours;
        $sprintReportData->spentSum = $spentSum;
        $sprintReportData->projectHours = $spentHours + $remainingHours;
        $sprintReportData->epics = $epics;
        $sprintReportData->sprints = $sprints;

        return $sprintReportData;
    }

    /**
     * Get from Jira.
     *
     * @TODO: Wrap the call in request function, they are 99% the same code.
     *
     * @throws ApiServiceException
     */
    private function get(string $path, array $query = []): mixed
    {
        try {
            $response = $this->jiraProjectTrackerApi->request('GET', $path,
                [
                    'query' => $query,
                ]
            );

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                    }
                    break;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            throw new ApiServiceException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Post to Jira.
     *
     * @throws ApiServiceException
     */
    private function post(string $path, array $data): mixed
    {
        try {
            $response = $this->jiraProjectTrackerApi->request('POST', $path,
                [
                    'json' => $data,
                ]
            );

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                case 201:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                    }
                    break;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            throw new ApiServiceException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Put to Jira.
     *
     * @throws ApiServiceException
     */
    private function put(string $path, array $data): mixed
    {
        try {
            $response = $this->jiraProjectTrackerApi->request('PUT', $path,
                [
                    'json' => $data,
                ]
            );

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                case 201:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                    }
                    break;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            throw new ApiServiceException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Delete in Jira.
     *
     * @throws ApiServiceException
     */
    private function delete(string $path): bool
    {
        try {
            $response = $this->jiraProjectTrackerApi->request('DELETE', $path);

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                case 204:
                    return true;
                case 400:
                case 401:
                case 403:
                case 409:
                    if ($body) {
                        $error = json_decode($body, null, 512, JSON_THROW_ON_ERROR);
                        if (!empty($error->errorMessages)) {
                            $msg = array_pop($error->errorMessages);
                        } else {
                            $msg = $error->errors->projectKey;
                        }
                        throw new ApiServiceException($msg);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            throw new ApiServiceException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return false;
    }

    /**
     * @throws ApiServiceException
     */
    public function getClientDataForProject(string $projectId): array
    {
        $clients = [];

        $accountIds = $this->getAccountIdsByProject($projectId);

        foreach ($accountIds as $accountId) {
            $account = $this->getAccount($accountId);

            $client = new ClientData();

            $client->projectTrackerId = $accountId;
            $client->name = $account->name;
            $client->contact = $account->contact->name ?? null;
            $client->account = $account->key ?? null;
            $client->customerKey = $account->customer->key ?? null;
            $client->salesChannel = $account->category->key ?? null;

            switch ($account->category->name ?? null) {
                case 'INTERN':
                    $client->type = ClientTypeEnum::INTERNAL;
                    $client->psp = $account->key;
                    break;
                case 'EKSTERN':
                    $client->type = ClientTypeEnum::EXTERNAL;
                    $client->ean = $account->key;
                    break;
            }

            $rateTable = $this->getRateTableByAccount($accountId);

            foreach ($rateTable->rates as $rate) {
                if ('DEFAULT_RATE' === ($rate->link->type ?? '')) {
                    $client->standardPrice = $rate->amount ?? null;
                    break;
                }
            }

            $clients[] = $client;
        }

        return $clients;
    }

    /**
     * @throws ApiServiceException
     */
    public function getAllProjectData(): array
    {
        $projects = [];

        $trackerProjects = $this->getAllProjects();

        foreach ($trackerProjects as $trackerProject) {
            $project = $this->getProject($trackerProject->id);
            $projectVersions = $project->versions ?? [];

            $projectData = new ProjectData();
            $projectData->name = $trackerProject->name;
            $projectData->projectTrackerId = $trackerProject->id;
            $projectData->projectTrackerKey = $trackerProject->key;
            $projectData->projectTrackerProjectUrl = $trackerProject->self;

            foreach ($projectVersions as $projectVersion) {
                $projectData->versions->add(new VersionData($projectVersion->id, $projectVersion->name));
            }

            $projects[] = $projectData;
        }

        return $projects;
    }

    /**
     * Get all worklogs for project.
     *
     * @param $projectId
     * @param string $from
     * @param string $to
     *
     * @return mixed
     *
     * @throws ApiServiceException
     */
    public function getProjectWorklogs($projectId, string $from = '2000-01-01', string $to = '3000-01-01'): mixed
    {
        return $this->post('rest/tempo-timesheets/4/worklogs/search', [
            'from' => $from,
            'to' => $to,
            'include' => ['ISSUE'],
            'projectId' => [$projectId],
        ]);
    }

    /**
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getWorklogDataForProject(string $projectId): array
    {
        $worklogsResult = [];

        $worklogs = $this->getProjectWorklogs($projectId);

        foreach ($worklogs as $worklog) {
            $worklogData = new WorklogData();
            $worklogData->projectTrackerId = $worklog->tempoWorklogId;
            $worklogData->worker = $worklog->worker;
            $worklogData->timeSpentSeconds = $worklog->timeSpentSeconds;
            $worklogData->comment = $worklog->comment;
            $worklogData->started = new \DateTime($worklog->started);
            $worklogData->projectTrackerIssueId = $worklog->issue->id;

            // TODO: Is this synchronization relevant?
            if (isset($worklog->attributes->_Billed_) && '_Billed_' == $worklog->attributes->_Billed_->key) {
                $worklogData->projectTrackerIsBilled = 'true' == $worklog->attributes->_Billed_->value;
            }

            $worklogsResult[] = $worklogData;
        }

        return $worklogsResult;
    }

    /**
     * @throws ApiServiceException
     */
    public function getAllAccountData(): array
    {
        $accountsResult = [];

        $accounts = $this->getAllAccounts();

        foreach ($accounts as $account) {
            $status = $account->status;
            $id = $account->id;
            $key = $account->key;
            $category = $account->category->name ?? null;
            $name = $account->name;

            $accountsResult[] = new AccountData($id, $name, $key, $category, $status);
        }

        return $accountsResult;
    }

    /**
     * @throws ApiServiceException
     */
    private function getProjectIssues($projectId): array
    {
        $issues = [];

        // Get customFields from Jira.
        $customFieldEpicLink = $this->getCustomFieldId('Epic Link');
        $customFieldAccount = $this->getCustomFieldId('Account');

        // Get all issues for version.
        $fields = implode(
            ',',
            [
                'timetracking',
                'worklog',
                'timespent',
                'timeoriginalestimate',
                'summary',
                'assignee',
                'status',
                'resolutiondate',
                'fixVersions',
                $customFieldEpicLink,
                $customFieldAccount,
            ]
        );

        $startAt = 0;

        // Get issues for the given project and version.
        do {
            $results = $this->get(
                self::API_PATH_SEARCH,
                [
                    'jql' => "project = $projectId",
                    'maxResults' => 50,
                    //          'fields' => $fields,
                    'startAt' => $startAt,
                ]
            );

            $issues = array_merge($issues, $results->issues);

            $startAt += 50;
        } while (isset($results->total) && $results->total > $startAt);

        return $issues;
    }

    /**
     * @throws ApiServiceException
     */
    private function getProjectIssuesPaged($projectId, $startAt, $maxResults = 50): array
    {
        // Get customFields from Jira.
        $customFieldEpicLink = $this->getCustomFieldId('Epic Link');
        $customFieldAccount = $this->getCustomFieldId('Account');

        // Get all issues for version.
        $fields = implode(
            ',',
            [
                'timetracking',
                'worklog',
                'timespent',
                'timeoriginalestimate',
                'summary',
                'assignee',
                'status',
                'resolutiondate',
                'fixVersions',
                $customFieldEpicLink,
                $customFieldAccount,
            ]
        );

        $results = $this->get(
            self::API_PATH_SEARCH,
            [
                'jql' => "project = $projectId",
                'maxResults' => $maxResults,
                // 'fields' => $fields,
                'startAt' => $startAt,
            ]
        );

        return [
            'issues' => $results->issues,
            'total' => $results->total,
            'startAt' => $startAt,
            'maxResults' => $maxResults,
        ];
    }

    public function getIssuesDataForProjectPaged(string $projectId, $startAt = 0, $maxResults = 50): array
    {
        // Get customFields from Jira.
        $customFieldEpicLinkId = $this->getCustomFieldId('Epic Link');
        $customFieldAccount = $this->getCustomFieldId('Account');

        $result = [];

        $pagedResult = $this->getProjectIssuesPaged($projectId, $startAt, $maxResults);

        $issues = $pagedResult['issues'];

        $epicsRetrieved = [];

        foreach ($issues as $issue) {
            $fields = $issue->fields;

            $issueData = new IssueData();
            $issueData->name = $fields->summary;
            $issueData->status = $fields->status->name;
            $issueData->projectTrackerId = $issue->id;
            $issueData->projectTrackerKey = $issue->key;
            $issueData->resolutionDate = isset($fields->resolutiondate) ? new \DateTime($fields->resolutiondate) : null;

            $issueData->accountId = $fields->{$customFieldAccount}->id ?? null;
            $issueData->accountKey = $fields->{$customFieldAccount}->key ?? null;

            if (isset($fields->{$customFieldEpicLinkId})) {
                $epicKey = $fields->{$customFieldEpicLinkId};

                if (isset($epicsRetrieved[$epicKey])) {
                    $epicData = $epicsRetrieved[$epicKey];
                } else {
                    $epicData = $this->getIssue($epicKey);
                    $epicsRetrieved[$epicKey] = $epicData;
                }

                $issueData->epicKey = $epicKey;
                $issueData->epicName = $epicData->fields->summary ?? null;
            }

            foreach ($fields->fixVersions ?? [] as $fixVersion) {
                $issueData->versions->add(new VersionData($fixVersion->id, $fixVersion->name));
            }

            $result[] = $issueData;
        }

        return [
            'issues' => $result,
            'startAt' => $startAt,
            'maxResults' => $maxResults,
            'total' => $pagedResult['total'],
        ];
    }

    /**
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getIssuesDataForProject(string $projectId): array
    {
        // Get customFields from Jira.
        $customFieldEpicLinkId = $this->getCustomFieldId('Epic Link');
        $customFieldAccount = $this->getCustomFieldId('Account');

        $result = [];

        $issues = $this->getProjectIssues($projectId);

        $epicsRetrieved = [];

        foreach ($issues as $issue) {
            $fields = $issue->fields;

            $issueData = new IssueData();
            $issueData->name = $fields->summary;
            $issueData->status = $fields->status->name;
            $issueData->projectTrackerId = $issue->id;
            $issueData->projectTrackerKey = $issue->key;
            $issueData->resolutionDate = isset($fields->resolutiondate) ? new \DateTime($fields->resolutiondate) : null;

            $issueData->accountId = $fields->{$customFieldAccount}->id ?? null;
            $issueData->accountKey = $fields->{$customFieldAccount}->key ?? null;

            if (isset($fields->{$customFieldEpicLinkId})) {
                $epicKey = $fields->{$customFieldEpicLinkId};

                if (isset($epicsRetrieved[$epicKey])) {
                    $epicData = $epicsRetrieved[$epicKey];
                } else {
                    $epicData = $this->getIssue($epicKey);
                    $epicsRetrieved[$epicKey] = $epicData;
                }

                $issueData->epicKey = $epicKey;
                $issueData->epicName = $epicData->fields->summary ?? null;
            }

            foreach ($fields->fixVersions ?? [] as $fixVersion) {
                $issueData->versions->add(new VersionData($fixVersion->id, $fixVersion->name));
            }

            $result[] = $issueData;
        }

        return $result;
    }

    /**
     * @throws ApiServiceException
     */
    private function getIssue(string $issueId): mixed
    {
        return $this->get("/rest/api/2/issue/$issueId");
    }

    private function getCustomFields(): mixed
    {
        return $this->get('/rest/api/2/customFields');
    }
}
