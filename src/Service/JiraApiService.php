<?php

namespace App\Service;

use App\Enum\ClientTypeEnum;
use App\Enum\IssueStatusEnum;
use App\Exception\ApiServiceException;
use App\Interface\DataProviderServiceInterface;
use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\IssueData;
use App\Model\Invoices\IssueDataCollection;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\ProjectDataCollection;
use App\Model\Invoices\VersionData;
use App\Model\Invoices\WorklogData;
use App\Model\Invoices\WorklogDataCollection;
use App\Model\Planning\PlanningData;
use App\Model\SprintReport\SprintReportData;
use App\Model\SprintReport\SprintReportEpic;
use App\Model\SprintReport\SprintReportIssue;
use App\Model\SprintReport\SprintReportProject;
use App\Model\SprintReport\SprintReportProjects;
use App\Model\SprintReport\SprintReportSprint;
use App\Model\SprintReport\SprintReportVersion;
use App\Model\SprintReport\SprintReportVersions;
use App\Model\SprintReport\SprintStateEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JiraApiService implements DataProviderServiceInterface
{
    private const NO_SPRINT = 'NoSprint';
    private const API_PATH_SEARCH = '/rest/api/2/search';
    private const API_PATH_VERSION = '/rest/api/2/version/';
    private const API_PATH_ACCOUNT = '/rest/tempo-accounts/1/account/';
    private const API_PATH_PROJECT = '/rest/api/2/project';
    private const API_PATH_PROJECT_BY_ID = '/rest/api/2/project/';
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

    public function getAllAccounts(): mixed
    {
        return $this->get(self::API_PATH_ACCOUNT);
    }

    public function getAllProjects(): mixed
    {
        return $this->get(self::API_PATH_PROJECT);
    }

    public function getProject($key): mixed
    {
        return $this->get(self::API_PATH_PROJECT_BY_ID.$key);
    }

    public function getSprintReportProjects(): SprintReportProjects
    {
        $sprintReportProjects = new SprintReportProjects();
        $projects = $this->getAllProjects();

        foreach ($projects as $project) {
            $sprintReportProject = new SprintReportProject();
            $sprintReportProject->id = $project->id;
            $sprintReportProject->name = $project->name;
            $sprintReportProjects->projects->add($sprintReportProject);
        }

        return $sprintReportProjects;
    }

    public function getSprintReportVersions(string $projectId): SprintReportVersions
    {
        $sprintReportVersions = new SprintReportVersions();
        $project = $this->getProject($projectId);
        $projectVersions = $project->versions ?? [];

        foreach ($projectVersions as $projectVersion) {
            $sprintReportVersion = new SprintReportVersion();
            $sprintReportVersion->id = $projectVersion->id;
            $sprintReportVersion->name = $projectVersion->name;
            $sprintReportVersion->projectTrackerId = $projectVersion->projectId;

            $sprintReportVersions->versions->add($sprintReportVersion);
        }

        return $sprintReportVersions;
    }

    public function getAccount(string $accountId): mixed
    {
        return $this->get(self::API_PATH_ACCOUNT.$accountId.'/');
    }

    public function getRateTableByAccount(string $accountId): mixed
    {
        return $this->get(self::API_PATH_RATE_TABLE, [
            'scopeId' => $accountId,
            'scopeType' => 'ACCOUNT',
        ]);
    }

    public function getAccountIdsByProject(string $projectId): array
    {
        $projectLinks = $this->get(self::API_PATH_ACCOUNT_IDS_BY_PROJECT.$projectId);

        return array_reduce($projectLinks, function ($carry, $item) {
            $carry[] = (string) $item->accountId;

            return $carry;
        }, []);
    }

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

    public function getPlanningDataWeeks(): PlanningData
    {
        throw new ApiServiceException('Method not implemented', 501);
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

                $assignedToSprint = $issue->assignedToSprint;
                $newRemainingWork = (float) ($issue->epic->remainingWork->containsKey($assignedToSprint->id) ? $issue->epic->remainingWork->get($assignedToSprint->id) : 0) + $remainingEstimateSeconds;
                $issue->epic->remainingWork->set($assignedToSprint->id, $newRemainingWork);
                $issue->epic->plannedWorkSum += $remainingEstimateSeconds;
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
            $client->customerKey = $account->customer->key ?? null;

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

    public function getProjectDataCollection(): ProjectDataCollection
    {
        $projectDataCollection = new ProjectDataCollection();
        $projects = $this->getAllProjects();

        foreach ($projects as $project) {
            $projectData = new ProjectData();
            $projectData->name = $project->name;
            $projectData->projectTrackerId = $project->id;
            $projectData->projectTrackerKey = $project->key;
            $projectData->projectTrackerProjectUrl = $project->self;

            $projectVersions = $this->getSprintReportVersions($project->id);
            foreach ($projectVersions as $projectVersion) {
                $projectData->versions?->add($projectVersion);
            }

            $projectDataCollection->projectData->add($projectData);
        }

        return $projectDataCollection;
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

    public function getWorklogDataCollection(string $projectId): WorklogDataCollection
    {
        $worklogDataCollection = new WorklogDataCollection();
        $worklogs = $this->getProjectWorklogs($projectId);

        foreach ($worklogs as $worklog) {
            $worklogData = new WorklogData();
            $worklogData->projectTrackerId = $worklog->tempoWorklogId;
            $worklogData->comment = $worklog->comment;
            $worklogData->worker = $worklog->worker;
            $worklogData->timeSpentSeconds = (int) $worklog->timeSpentSeconds;
            $worklogData->started = new \DateTime($worklog->started);
            $worklogData->projectTrackerIsBilled = false;
            $worklogData->projectTrackerIssueId = $worklog->issue->id;

            $worklogDataCollection->worklogData->add($worklogData);

            // TODO: Is this synchronization relevant?
            if (isset($worklog->attributes->_Billed_) && '_Billed_' == $worklog->attributes->_Billed_->key) {
                $worklogData->projectTrackerIsBilled = 'true' == $worklog->attributes->_Billed_->value;
            }
        }

        return $worklogDataCollection;
    }

    public function getAllAccountData(): array
    {
        $accountsResult = [];

        $accounts = $this->getAllAccounts();

        foreach ($accounts as $account) {
            $id = $account->id;
            $key = $account->key;
            $name = $account->name;

            $accountsResult[] = new AccountData($id, $name, $key);
        }

        return $accountsResult;
    }

    /**
     * @throws ApiServiceException
     */
    private function getProjectIssuesPaged($projectId, $startAt, $maxResults = 50): array
    {
        $results = $this->get(
            self::API_PATH_SEARCH,
            [
                'jql' => "project = $projectId",
                'maxResults' => $maxResults,
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

    public function getIssuesDataForProjectPaged(string $projectId, $startAt = 0, $maxResults = 50): PagedResult
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
            $issueData->status = $this->convertStatusToEnum($fields->status->name);
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

                $issueData->epics = null !== $issueData->epicName ? [$issueData->epicName] : [];
            }

            foreach ($fields->fixVersions ?? [] as $fixVersion) {
                $issueData->versions?->add(new VersionData($fixVersion->id, $fixVersion->name));
            }

            $result[] = $issueData;
        }

        return new PagedResult($result, $startAt, $maxResults, $pagedResult['total']);
    }

    private function convertStatusToEnum(string $statusName)
    {
        $statusMapping = [
            'Lukket' => IssueStatusEnum::DONE,
            'Åben' => IssueStatusEnum::NEW,
            'Afventer' => IssueStatusEnum::WAITING,
            'I gang' => IssueStatusEnum::IN_PROGRESS,
            'Til test' => IssueStatusEnum::READY_FOR_TEST,
            'Klar til planlægning' => IssueStatusEnum::READY_FOR_PLANNING,
            'Klar til release' => IssueStatusEnum::READY_FOR_RELEASE,
            'Til review' => IssueStatusEnum::IN_REVIEW,
            'Done' => IssueStatusEnum::DONE,
            'To Do' => IssueStatusEnum::NEW,
            'In Progress' => IssueStatusEnum::IN_PROGRESS,
            'Closed' => IssueStatusEnum::DONE,
        ];

        if (array_key_exists($statusName, $statusMapping)) {
            return $statusMapping[$statusName];
        }

        // Default fallback for unmatched statuses
        return IssueStatusEnum::OTHER;
    }

    /**
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getIssueDataCollection(string $projectId): IssueDataCollection
    {
        throw new ApiServiceException('Method not implemented', 501);
    }

    /**
     * @throws ApiServiceException
     */
    public function getworklogdataforprojectpaged(string $projectId, $startAt = 0, $maxResults = 50): PagedResult
    {
        throw new ApiServiceException('Method not implemented', 501);
    }

    /**
     * @throws ApiServiceException
     */
    private function getIssue(string $issueId): mixed
    {
        return $this->get("/rest/api/2/issue/$issueId");
    }
}
