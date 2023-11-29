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
use App\Model\SprintReport\SprintReportIssue;
use App\Model\SprintReport\SprintReportSprint;
use App\Model\SprintReport\SprintReportTag;
use App\Model\SprintReport\SprintStateEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JiraApiService implements ApiServiceInterface
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

    private const API_PATH_JSONRPC = '/api/jsonrpc/';

    private const PAST = 'PAST';
    private const PRESENT = 'PRESENT';
    private const FUTURE = 'FUTURE';

    public function __construct(
        protected readonly HttpClientInterface $projectTrackerApi,
        protected readonly array $customFieldMappings,
        protected readonly string $defaultBoard,
        protected readonly string $leantimeUrl,
        protected readonly float $weekGoalLow,
        protected readonly float $weekGoalHigh,
        protected readonly string $sprintNameRegex,
    ) {
    }

    public function getEndpoints(): array
    {
        return [
            'base' => $this->leantimeUrl,
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
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getAll', []);
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
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getProject', ['id' => $key]);
    }

    /**
     * Get milestone.
     *
     * @param $key
     *   A milestone key or id
     *
     * @throws ApiServiceException
     */
    public function getMilestone($key): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getTicket', ['id' => $key]);
    }

    public function getProjectMilestones($key): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAllMilestones', ['searchCriteria' => ['currentProject' => $key, 'type' => 'milestone']]);
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
     * @throws ApiServiceException
     */
    public function getAllSprints(): array
    {
        $sprints = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.sprints.getAllSprints', ['projectId' => '6']);

        return $sprints;
    }

    /**
     * Get all issues for given board and sprint.
     *
     * @param string $sprintId id of the sprint to extract issues for
     *
     * @return array array of issues
     *
     * @throws ApiServiceException
     */
    public function getTicketsInSprint(string $sprintId): array
    {
        $result = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', [
            'searchCriteria' => [
                'sprint' => $sprintId,
            ],
        ]);

        return $result;
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

        $sprintIssues = [];

        $allSprints = $this->getAllSprints();

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

            $issues = $this->getTicketsInSprint($sprintData->id);

            $sprintIssues[$sprintData->id] = $issues;
        }

        foreach ($sprintIssues as $sprintId => $issues) {
            foreach ($issues as $issueData) {
                if ('0' !== $issueData->status) {
                    $projectKey = (string) $issueData->projectId;
                    $projectDisplayName = $issueData->projectName;
                    $hoursRemaining = $issueData->hourRemaining;

                    if (empty($issueData->editorId)) {
                        $assigneeKey = 'unassigned';
                        $assigneeDisplayName = 'Unassigned';
                    } else {
                        $assigneeKey = (string) $issueData->editorId;
                        $assigneeDisplayName = $issueData->editorFirstname.' '.$issueData->editorLastname;
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
                    $sprintSum->sumHours += $hoursRemaining;

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
                        $projectSprintSum->sumHours += $hoursRemaining;
                    }

                    $assigneeProject->issues->add(
                        new Issue(
                            $issueData->id,
                            $issueData->description,
                            isset($issueData->hourRemaining) ? $hoursRemaining : null,
                            $this->leantimeUrl.'/dashboard/home#/tickets/showTicket/'.$issueData->id,
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
                    $projectSprintSum->sumHours += $hoursRemaining;

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
                    $projectAssigneeSprintSum->sumHours += $hoursRemaining;

                    $projectAssignee->issues->add(new Issue(
                        $issueData->id,
                        $issueData->description,
                        isset($issueData->hourRemaining) ? $hoursRemaining : null,
                        $this->leantimeUrl.'/dashboard/home#/tickets/showTicket/'.$issueData->id,
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
    private function getIssuesForProjectMilestone($projectId, $milestoneId): array
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', ['searchCriteria' => ['currentProject' => $projectId, 'milestone' => $milestoneId]]);
    }

    /**
     * @throws ApiServiceException
     */
    private function getIssueSprint($issueEntry): SprintReportSprint
    {
        $sprint = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.sprints.getSprint', ['id' => $issueEntry->sprint]);

        if ($sprint) {
            $sprintState = SprintStateEnum::OTHER;
            $sprintTemporal = $this->getDateSpanTemporal($sprint->startDate, $sprint->endDate);

            switch ($sprintTemporal) {
                case self::PAST:
                    $sprintState = SprintStateEnum::OTHER;
                    break;
                case self::PRESENT:
                    $sprintState = SprintStateEnum::ACTIVE;
                    break;
                case self::FUTURE:
                    $sprintState = SprintStateEnum::FUTURE;
                    break;
            }

            return new SprintReportSprint(
                $sprint->id,
                $sprint->name,
                $sprintState,
                $sprint->startDate ? strtotime($sprint->startDate) : null,
                $sprint->endDate ? strtotime($sprint->endDate) : null,
            );
        } else {
            throw new ApiServiceException('Sprint not found', 404);
        }
    }

    /**
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getSprintReportData(string $projectId, string $milestoneId): SprintReportData
    {
        $sprintReportData = new SprintReportData();
        $tags = $sprintReportData->tags;
        $issues = $sprintReportData->issues;
        $sprints = $sprintReportData->sprints;

        $spentSum = 0;
        $remainingSum = 0;

        $tags->set('noTag', new SprintReportTag('noTag', 'Uden Tag'));

        // Get version and project.
        $milestone = $this->getMilestone($milestoneId);
        $project = $this->getProject($projectId);

        $issueEntries = $this->getIssuesForProjectMilestone($projectId, $milestoneId);

        $issueCount = 1;
        foreach ($issueEntries as $issueEntry) {
            $issue = new SprintReportIssue();
            $issues->add($issue);

            // Set issue tag.
            if (isset($issueEntry->tags)) {
                $tagId = $this->tagToId($issueEntry->tags);
                $tag = new SprintReportTag($tagId, $issueEntry->tags);
                $tags->set($tagId, $tag);
            } else {
                $tag = $tags->get('NoTag');
            }

            if (!$tag instanceof SprintReportTag) {
                continue;
            }

            $issue->tag = $tag;

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
            $worklogs = $this->getTimesheetsForTicket($issueEntry->id);

            foreach ($worklogs as $worklog) {
                $workLogStarted = strtotime($worklog->workDate);

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
                $newLoggedWork = (float) ($issue->tag->loggedWork->containsKey($worklogSprintId) ? $issue->tag->loggedWork->get($worklogSprintId) : 0) + $worklog->hours;
                $issue->tag->loggedWork->set($worklogSprintId, $newLoggedWork);
            }

            // Accumulate spentSum.
            $spentSum += $issueEntry->bookedHours;
            $issue->tag->spentSum += $issueEntry->bookedHours;

            // Accumulate remainingSum.
            if ('0' !== $issueEntry->status && isset($issueEntry->hourRemaining)) {
                $remainingEstimateSeconds = $issueEntry->hourRemaining;
                $remainingSum += $remainingEstimateSeconds;

                $issue->tag->remainingSum += $remainingEstimateSeconds;

                if (!empty($issue->assignedToSprint)) {
                    $assignedToSprint = $issue->assignedToSprint;
                    $newRemainingWork = (float) ($issue->tag->remainingWork->containsKey($assignedToSprint->id) ? $issue->tag->remainingWork->get($assignedToSprint->id) : 0) + $remainingEstimateSeconds;
                    $issue->tag->remainingWork->set($assignedToSprint->id, $newRemainingWork);
                    $issue->tag->plannedWorkSum += $remainingEstimateSeconds;
                }
            }
            // Accumulate originalEstimateSum.
            if (isset($issueEntry->planHours)) {
                $issue->tag->originalEstimateSum += $issueEntry->planHours;

                $sprintReportData->originalEstimateSum += $issueEntry->planHours;
            }
            ++$issueCount;
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
        $iterator = $tags->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->name) <=> mb_strtolower($b->name);
        });
        $tags = new ArrayCollection(iterator_to_array($iterator));
        // Calculate spent, remaining hours.
        $spentHours = $spentSum;
        $remainingHours = $remainingSum;

        $sprintReportData->projectName = $project->name;
        $sprintReportData->milestoneName = $milestone->headline;
        $sprintReportData->remainingHours = $remainingHours;
        $sprintReportData->spentHours = $spentHours;
        $sprintReportData->spentSum = $spentSum;
        $sprintReportData->projectHours = $spentHours + $remainingHours;
        $sprintReportData->tags = $tags;
        $sprintReportData->sprints = $sprints;

        return $sprintReportData;
    }

    /**
     * Get from Leantime.
     *
     * @throws ApiServiceException
     */
    private function request(string $path, string $type, string $method, array $params = []): mixed
    {
        try {
            $response = $this->projectTrackerApi->request($type, $path,
                ['json' => [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'id' => '1',
                    'params' => $params,
                ]]
            );

            $body = $response->getContent(false);
            switch ($response->getStatusCode()) {
                case 200:
                    if ($body) {
                        return json_decode($body, null, 512, JSON_THROW_ON_ERROR)->result;
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

    public function getTimesheetsForTicket($ticketId): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.timesheets.getAll', ['invEmpl' => '-1', 'invComp' => '-1', 'paid' => '-1', 'ticketFilter' => $ticketId]);
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
     * @throws \Exception
     */
    public function getIssuesDataForProject(string $projectId): array
    {
        // Get customFields from Jira.
        $customFieldEpicLinkId = $this->getCustomFieldId('Epic Link');
        $customFieldAccount = $this->getCustomFieldId('Account');

        $result = [];

        $project = $this->getProject($projectId);
        $versions = $project->versions ?? [];

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
                /** @var array<\stdClass> $versionsFound */
                $versionsFound = array_filter($versions, function ($v) use ($fixVersion) {
                    return $v->id == $fixVersion->id;
                });

                if (count($versionsFound) > 0) {
                    $versions = array_values($versionsFound);

                    foreach ($versions as $version) {
                        if (isset($version->id) && isset($version->name)) {
                            $issueData->versions->add(new VersionData($version->id, $version->name));
                        }
                    }
                }
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

    private function getDateSpanTemporal($startDate, $endDate): string
    {
        $currentDate = time();
        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);
        if ($startDate < $currentDate && $endDate > $currentDate) {
            return self::PRESENT;
        } elseif ($startDate < $currentDate && $endDate < $currentDate) {
            return self::PAST;
        } else {
            return self::FUTURE;
        }
    }

    private function tagToId($tag)
    {
        // Use md5 hash function to generate a fixed-length hash
        $hash = md5($tag);

        // Extract three unique numbers from the hash
        $num1 = hexdec(substr($hash, 0, 8)) % 1000;
        $num2 = hexdec(substr($hash, 8, 8)) % 1000;
        $num3 = hexdec(substr($hash, 16, 8)) % 1000;

        return "$num1$num2$num3";
    }
}
