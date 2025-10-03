<?php

namespace App\Service;

use App\Enum\ClientTypeEnum;
use App\Enum\IssueStatusEnum;
use App\Exception\ApiServiceException;
use App\Interface\DataProviderInterface;
use App\Model\Invoices\AccountData;
use App\Model\Invoices\ClientData;
use App\Model\Invoices\IssueData;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\ProjectDataCollection;
use App\Model\Invoices\VersionData;
use App\Model\Invoices\VersionModel;
use App\Model\Invoices\Versions;
use App\Model\Invoices\WorklogData;
use App\Model\Invoices\WorklogDataCollection;
use App\Model\Planning\PlanningData;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class JiraApiService implements DataProviderInterface
{
    private const API_PATH_SEARCH = '/rest/api/2/search';
    private const API_PATH_ACCOUNT = '/rest/tempo-accounts/1/account/';
    private const API_PATH_PROJECT = '/rest/api/2/project';
    private const API_PATH_PROJECT_BY_ID = '/rest/api/2/project/';
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

    public function updateAll(bool $asyncJobQueue = true): void
    {
        $this->updateProjects($asyncJobQueue);
        $this->updateVersions($asyncJobQueue);
        $this->updateIssues($asyncJobQueue);
        $this->updateWorklogs($asyncJobQueue);
    }

    public function updateProjects(bool $asyncJobQueue = true): void
    {
        // TODO: Implement updateProjects() method.
    }

    public function updateVersions(bool $asyncJobQueue = true): void
    {
        // TODO: Implement updateVersions() method.
    }

    public function updateIssues(bool $asyncJobQueue = true): void
    {
        // TODO: Implement updateIssues() method.
    }

    public function updateWorklogs(bool $asyncJobQueue = true): void
    {
        // TODO: Implement updateWorklogs() method.
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

    public function getProjectVersions(string $projectId): Versions
    {
        $versions = new Versions();
        $project = $this->getProject($projectId);
        $projectVersions = $project->versions ?? [];

        foreach ($projectVersions as $projectVersion) {
            $version = new VersionModel();
            $version->id = $projectVersion->id;
            $version->name = $projectVersion->name;
            $version->projectTrackerId = $projectVersion->projectId;

            $versions->versions->add($version);
        }

        return $versions;
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

            $projectVersions = $this->getProjectVersions($project->id);
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
     */
    private function getIssue(string $issueId): mixed
    {
        return $this->get("/rest/api/2/issue/$issueId");
    }
}
