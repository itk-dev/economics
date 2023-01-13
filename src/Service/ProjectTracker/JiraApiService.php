<?php

namespace App\Service\ProjectTracker;

use App\Exception\ApiServiceException;
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
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JiraApiService implements ApiServiceInterface
{
    private const CPB_ACCOUNT_MANAGER = 'anbjv';

    public function __construct(
        protected readonly HttpClientInterface $projectTrackerApi,
        protected readonly array $customFieldMappings,
        protected readonly string $defaultBoard,
        protected readonly string $jiraUrl,
        protected readonly float $weekGoalLow,
        protected readonly float $weekGoalHigh,
    ) {
    }

    /**
     * @throws ApiServiceException
     */
    public function getAllProjectCategories(): mixed
    {
        return $this->get('/rest/api/2/projectCategory');
    }

    /**
     * Get all accounts.
     *
     * @throws ApiServiceException
     */
    public function getAllAccounts(): mixed
    {
        return $this->get('/rest/tempo-accounts/1/account/');
    }

    /**
     * Get all accounts.
     *
     * @throws ApiServiceException
     */
    public function getAllCustomers(): mixed
    {
        return $this->get('/rest/tempo-accounts/1/customer/');
    }

    /**
     * Get all projects, including archived.
     *
     * @throws ApiServiceException
     */
    public function getAllProjects(): mixed
    {
        return $this->get('/rest/api/2/project');
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
        return $this->get('/rest/api/2/project/'.$key);
    }

    /**
     * Get current user permissions.
     *
     * @throws ApiServiceException
     */
    public function getCurrentUserPermissions(): mixed
    {
        return $this->get('/rest/api/2/mypermissions');
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

        $response = $this->post('/rest/api/2/project', $project);

        return $response->key == $projectKey ? $projectKey : null;
    }

    /**
     * Create a jira customer.
     *
     * @throws ApiServiceException
     */
    public function createTimeTrackerCustomer(string $name, string $key): mixed
    {
        return $this->post('/rest/tempo-accounts/1/customer/',
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
        return $this->post('/rest/tempo-accounts/1/account/',
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
        return $this->get('/rest/tempo-accounts/1/account/key/'.$key);
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
        $this->post('/rest/tempo-accounts/1/link/', [
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
        $filterResponse = $this->post('/rest/api/2/filter', [
            'name' => 'Filter for Project: '.$project->name,
            'description' => 'Project filter for '.$project->name,
            'jql' => 'project = '.$project->key.' ORDER BY Rank ASC',
            'favourite' => false,
            'editable' => false,
        ]);

        // Share project filter with project members.
        $this->post('/rest/api/2/filter/'.$filterResponse->id.'/permission', [
            'type' => 'project',
            'projectId' => $project->id,
            'view' => true,
            'edit' => false,
        ]);

        // Create board with project filter.
        $this->post('/rest/agile/1.0/board', [
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
    public function getAccount(int $accountId): mixed
    {
        return $this->get('/rest/tempo-accounts/1/account/'.$accountId.'/');
    }

    /**
     * @throws ApiServiceException
     */
    public function getRateTableByAccount(int $accountId): mixed
    {
        return $this->get('/rest/tempo-accounts/1/ratetable', [
            'scopeId' => $accountId,
            'scopeType' => 'ACCOUNT',
        ]);
    }

    /**
     * @throws ApiServiceException
     */
    public function getAccountIdsByProject(int $projectId): mixed
    {
        $projectLinks = $this->get('/rest/tempo-accounts/1/link/project/'.$projectId);

        return array_reduce($projectLinks, function ($carry, $item) {
            $carry[] = $item->accountId;

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
        return $this->get('/rest/agile/1.0/board');
    }

    /**
     * Get all sprints for a given board.
     *
     * @param string $boardId board id
     * @param string $state   sprint state. Defaults to future,active sprints.
     *
     * @throws ApiServiceException
     */
    public function getAllSprints(string $boardId, string $state = 'future,active'): array
    {
        $sprints = [];

        $startAt = 0;
        while (true) {
            $result = $this->get('/rest/agile/1.0/board/'.$boardId.'/sprint', [
                'startAt' => $startAt,
                'maxResults' => 50,
                'state' => $state,
            ]);
            $sprints = array_merge($sprints, $result->values);

            if ($result->isLast) {
                break;
            }

            $startAt = $startAt + 50;
        }

        return $sprints;
    }

    /**
     * Get all issues for given board and sprint.
     *
     * @param string $boardId  id of the jira board to extract issues from
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
            $result = $this->get('/rest/agile/1.0/board/'.$boardId.'/sprint/'.$sprintId.'/issue', [
                'startAt' => $startAt,
                'fields' => $fields,
            ]);
            $issues = array_merge($issues, $result->issues);

            $startAt = $startAt + 50;

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
     * @throws Exception
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

            $pattern = "/(?<weeks>(?:-?\d+-?)*)\.(?<year>\d+)$/";

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
                    $project = $issueData->fields->project;
                    $projectKey = $project->key;
                    $projectDisplayName = $project->name;
                    $remainingSeconds = $issueData->fields->timetracking->remainingEstimateSeconds ?? 0;

                    if (empty($issueData->fields->assignee)) {
                        $assigneeKey = 'unassigned';
                        $assigneeDisplayName = 'Unassigned';
                    } else {
                        $assigneeKey = $issueData->fields->assignee->key;
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

                    /** @var SprintSum $sprintSum */
                    $projectSprintSum = $assigneeProject->sprintSums->get($sprintId);
                    $projectSprintSum->sumSeconds += $remainingSeconds;
                    $projectSprintSum->sumHours = $projectSprintSum->sumSeconds / (60 * 60);

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
        $iterator = $assignees->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->displayName) <=> mb_strtolower($b->displayName);
        });
        $planning->assignees = new ArrayCollection(iterator_to_array($iterator));

        // Sort projects by name.
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

    private function getIssuesForProjectVersion($projectId, $versionId): array
    {
        $issues = [];

        // Get customFields from Jira.
        $customFieldEpicLinkId = $this->getCustomFieldId('Epic Link');
        $customFieldSprintId = $this->getCustomFieldId('Sprint');

        // Get all issues for version.
        $startAt = 0;
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

        // Get issues for the given project and version.
        while (true) {
            $results = $this->get(
                '/rest/api/2/search',
                [
                    'jql' => 'fixVersion='.$versionId,
                    'project' => $projectId,
                    'maxResults' => 50,
                    'fields' => $fields,
                    'startAt' => $startAt,
                ]
            );

            $issues = array_merge($issues, $results->issues);

            $startAt = $startAt + 50;

            if ($results->total < $startAt) {
                break;
            }
        }

        return $issues;
    }

    private function getIssueSprint($issueEntry): ?SprintReportSprint
    {
        $customFieldSprintId = $this->getCustomFieldId('Sprint');

        // Get sprints for issue.
        if (isset($issueEntry->fields->{$customFieldSprintId})) {
            foreach ($issueEntry->fields->{$customFieldSprintId} as $sprintString) {
                $replace = preg_replace(
                    ['/.*\[/', '/].*/'],
                    '',
                    $sprintString
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

                return new SprintReportSprint(
                    $sprint['id'],
                    $sprint['name'],
                    $sprint['state'],
                    $sprint['startDate'] ? strtotime($sprint['startDate']) : null,
                    $sprint['endDate'] ? strtotime($sprint['endDate']) : null,
                    $sprint['completeDate'] ? strtotime($sprint['completeDate']) : null,
                );
            }
        }

        return null;
    }

    /**
     * @throws ApiServiceException
     * @throws Exception
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
        $version = $this->get('/rest/api/2/version/'.$versionId);
        $project = $this->getProject($projectId);

        // Get customField for Jira.
        $customFieldEpicLinkId = $this->getCustomFieldId('Epic Link');

        $issueEntries = $this->getIssuesForProjectVersion($projectId, $versionId);

        foreach ($issueEntries as $issueEntry) {
            $issue = new SprintReportIssue();
            $issues->add($issue);

            // Set issue epic.
            if (isset($issueEntry->fields->{$customFieldEpicLinkId})) {
                $epicLinkId = $issueEntry->fields->{$customFieldEpicLinkId};

                // Add to epics if not already added.
                if (!$epics->containsKey($epicLinkId)) {
                    $epicData = $this->get('rest/agile/1.0/epic/'.$epicLinkId);

                    $epic = new SprintReportEpic($epicLinkId, $epicData->name);
                    $epics->set($epicLinkId, $epic);
                }

                $issue->epic = $epics->get($issueEntry->fields->{$customFieldEpicLinkId});
            } else {
                $issue->epic = $epics->get('NoEpic');
            }

            // Get sprint for issue.
            $issueSprint = $this->getIssueSprint($issueEntry);

            // Add to sprints if not already added.
            if (isset($issueSprint)) {
                if (!$sprints->containsKey($issueSprint->id)) {
                    $sprints->set($issueSprint->id, $issueSprint);
                }

                // Set which sprint the issue is assigned to.
                if ('ACTIVE' === $issueSprint->state || 'FUTURE' === $issueSprint->state) {
                    $issue->assignedToSprint = $issueSprint;
                }
            }

            foreach ($issueEntry->fields->worklog->worklogs as $worklogData) {
                $workLogStarted = strtotime($worklogData->started);

                $worklogSprints = array_filter($sprints->toArray(), function ($sprintEntry) use ($workLogStarted) {
                    /* @var SprintReportSprint $sprintEntry */
                    return
                        $sprintEntry->startDateTimestamp <= $workLogStarted &&
                        ($sprintEntry->completedDateTimstamp ?? $sprintEntry->endDateTimestamp) > $workLogStarted;
                });

                $worklogSprintId = 'NoSprint';

                if (!empty($worklogSprints)) {
                    $worklogSprint = array_pop($worklogSprints);

                    $worklogSprintId = $worklogSprint->id;
                }

                $issue->epic->loggedWork->set($worklogSprintId, ($issue->epic->loggedWork->containsKey($worklogSprintId) ? $issue->epic->loggedWork->get($worklogSprintId) : 0) + $worklogData->timeSpentSeconds);
            }

            // Accumulate spentSum.
            $spentSum = $spentSum + $issueEntry->fields->timespent;
            $issue->epic->spentSum = $issue->epic->spentSum + $issueEntry->fields->timespent;

            // Accumulate remainingSum.
            if ('Done' !== !$issueEntry->fields->status->name && isset($issueEntry->fields->timetracking->remainingEstimateSeconds)) {
                $remainingEstimateSeconds = $issueEntry->fields->timetracking->remainingEstimateSeconds;
                $remainingSum = $remainingSum + $remainingEstimateSeconds;

                $issue->epic->remainingSum = $issue->epic->remainingSum + $remainingEstimateSeconds;

                if (!empty($issue->assignedToSprint)) {
                    $assignedToSprint = $issue->assignedToSprint;
                    $issue->epic->remainingWork->set($assignedToSprint->id, ($issue->epic->remainingWork->containsKey($assignedToSprint->id) ? $issue->epic->remainingWork->get($assignedToSprint->id) : 0) + $remainingEstimateSeconds);
                    $issue->epic->plannedWorkSum = $issue->epic->plannedWorkSum + $remainingEstimateSeconds;
                }
            }

            // Accumulate originalEstimateSum.
            if (isset($issueEntry->fields->timeoriginalestimate)) {
                $issue->epic->originalEstimateSum = $issue->epic->originalEstimateSum + $issueEntry->fields->timeoriginalestimate;

                $sprintReportData->originaltEstimatSum += $issueEntry->fields->timeoriginalestimate;
            }
        }

        // Sort sprints by key.
        $iterator = $sprints->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->id) <=> mb_strtolower($b->id);
        });
        $sprints = new ArrayCollection(iterator_to_array($iterator));

        // Sort epics by name.
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
            $response = $this->projectTrackerApi->request('GET', $path,
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
        } catch (Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
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
            $response = $this->projectTrackerApi->request('POST', $path,
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
        } catch (Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
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
            $response = $this->projectTrackerApi->request('PUT', $path,
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
        } catch (Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
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
            $response = $this->projectTrackerApi->request('DELETE', $path);

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
        } catch (Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
            throw new ApiServiceException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return false;
    }
}
