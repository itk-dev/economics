<?php

namespace App\Service\ProjectTracker;

use App\Exception\ApiServiceException;
use DateTime;
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
        $customFieldMappings
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
     * @throws ApiServiceException
     */
    public function getAllBoards(): mixed {
        return $this->get('/rest/agile/1.0/board');
    }

    /**
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
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getPlanningData(): array
    {
        // TODO: Make boardId configurable.
        $boardId = 30;

        $sprints = [];
        $sprintIssues = [];
        $sprintCells = [];

        $allSprints = $this->getAllSprints($boardId);

        $weekGoalLow = 20;
        $weekGoalHigh = 30;

        foreach ($allSprints as $sprint) {
            // Expected sprint name examples.
            // $a = "DEV sprint uge 2-3-4.23";
            // $b = "ServiceSupport uge 5.23";

            $pattern = "/(?<weeks>(?:-?\d+-?)*)\.(?<year>\d+)$/";

            $matches = [];

            preg_match_all($pattern, $sprint->name, $matches);

            if (!empty($matches['weeks'])) {
                $weeks = count(explode('-', $matches['weeks'][0]));
            } else {
                $weeks = 1;
            }

            $sprints[] = [
                'id' => $sprint->id,
                'weeks' => $weeks,
                'sprintGoalLow' => $weekGoalLow * $weeks,
                'sprintGoalHigh' => $weekGoalHigh * $weeks,
                'name' => $sprint->name,
            ];

            $issues = $this->getIssuesInSprint($boardId, $sprint->id);

            $sprintIssues[$sprint->id] = $issues;
        }

        $assignees = [];

        foreach ($sprintIssues as $sprintId => $issues) {
            foreach ($issues as $issue) {
                if ($issue->fields->status->statusCategory->key !== 'done') {
                    if (empty($issue->fields->assignee)) {
                        $assigneeKey = 'unassigned';
                        $assigneeDisplayName = 'Unassigned';
                    }
                    else {
                        $assigneeKey = $issue->fields->assignee->key;
                        $assigneeDisplayName = $issue->fields->assignee->displayName;
                    }

                    if (!array_key_exists($assigneeKey, $assignees)) {
                        $assignees[$assigneeKey] = (object) [
                            'key' => $assigneeKey,
                            'displayName' => $assigneeDisplayName,
                        ];
                    }

                    if (!isset($sprintCells[$assigneeKey][$sprintId])) {
                        $sprintCells[$assigneeKey][$sprintId] = 0;
                    }

                    $sprintCells[$assigneeKey][$sprintId] = $sprintCells[$assigneeKey][$sprintId] +
                        ($issue->fields->timetracking->remainingEstimateSeconds ?? 0);
                }
            }
        }

        usort($assignees, function ($a, $b) {
            return mb_strtolower($a->displayName) > mb_strtolower($b->displayName);
        });

        return [
            'sprints' => $sprints,
            'assignees' => $assignees,
            'sprintCells' => $sprintCells,
        ];
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
        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
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
        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
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
        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
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
        } catch (\Exception|ClientExceptionInterface|RedirectionExceptionInterface|TransportExceptionInterface|ServerExceptionInterface $e) {
            throw new ApiServiceException($e->getMessage(), (int) $e->getCode(), $e);
        }

        return false;
    }
}
