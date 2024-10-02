<?php

namespace App\Service;

use App\Enum\IssueStatusEnum;
use App\Exception\ApiServiceException;
use App\Interface\DataProviderServiceInterface;
use App\Model\Invoices\IssueData;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\ProjectDataCollection;
use App\Model\Invoices\VersionData;
use App\Model\Invoices\WorklogData;
use App\Model\Invoices\WorklogDataCollection;
use App\Model\Planning\Issue;
use App\Model\Planning\Project;
use App\Model\SprintReport\SprintReportVersion;
use App\Model\SprintReport\SprintReportVersions;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LeantimeApiService implements DataProviderServiceInterface
{
    private const API_PATH_JSONRPC = '/api/jsonrpc/';
    private const LEANTIME_TIMEZONE = 'UTC';

    private static ?\DateTimeZone $leantimeTimeZone = null;

    private const STATUS_MAPPING = [
        'NEW' => IssueStatusEnum::NEW,
        'INPROGRESS' => IssueStatusEnum::IN_PROGRESS,
        'DONE' => IssueStatusEnum::DONE,
        'NONE' => IssueStatusEnum::ARCHIVED,
    ];

    public function __construct(
        protected readonly HttpClientInterface $leantimeProjectTrackerApi,
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
     * Retrieves the ticket status settings for a specific project.
     *
     * @param string $projectId the ID of the project
     *
     * @return \stdClass the ticket status settings of the project
     *
     * @throws ApiServiceException
     */
    private function getProjectTicketStatusSettings(string $projectId): \stdClass
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getStatusLabels', ['projectId' => $projectId]);
    }

    /**
     * Get all worklogs for project.
     *
     * @param $projectId
     *
     * @return mixed
     */
    public function getProjectWorklogs($projectId): mixed
    {
        return $this->getTimesheets([
            'id' => $projectId,
        ]);
    }

    /**
     * @throws ApiServiceException
     */
    private function getProjectIssuesPaged($projectId, $startAt, $maxResults = 50): array
    {
        // TODO: Implement pagination.
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', ['searchCriteria' => ['currentProject' => $projectId]]);
    }

    /**
     * @throws ApiServiceException
     */
    public function getIssuesDataForProjectPaged(string $projectId, $startAt = 0, $maxResults = 50): PagedResult
    {
        $result = [];

        $projectTicketStatusSettings = $this->getProjectTicketStatusSettings($projectId);

        $issues = $this->getProjectIssuesPaged($projectId, $startAt, $maxResults);

        $workersData = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.users.getAll');

        $workers = array_reduce($workersData, function ($carry, $item) {
            $carry[$item->id] = $item->username;

            return $carry;
        }, []);
        foreach ($issues as $issue) {
            $issueData = new IssueData();

            $issueData->name = $issue->headline;
            $issueData->status = $this->convertStatusToEnum($issue->status, $projectTicketStatusSettings);
            $issueData->projectTrackerId = $issue->id;
            // Leantime does not have a key for each issue.
            $issueData->projectTrackerKey = $issue->id;
            $issueData->accountId = '';
            $issueData->accountKey = '';
            $issueData->epicKey = $issue->tags;
            $issueData->epicName = $issue->tags;
            $issueData->planHours = $issue->planHours;
            $issueData->hourRemaining = $issue->hourRemaining;
            $issueData->dueDate = !empty($issue->dateToFinish) && '0000-00-00 00:00:00' !== $issue->dateToFinish ? new \DateTime($issue->dateToFinish, self::getLeantimeTimeZone()) : null;
            if (isset($issue->milestoneid) && isset($issue->milestoneHeadline)) {
                $issueData->versions?->add(new VersionData($issue->milestoneid, $issue->milestoneHeadline));
            }
            $issueData->projectId = $issue->projectId;
            $issueData->resolutionDate = $this->getLeanDateTime($issue->editTo);
            $issueData->worker = $workers[$issue->editorId] ?? $issue->editorId;
            $issueData->linkToIssue = $this->leantimeUrl.'/tickets/showKanban?showTicketModal='.$issue->id.'#/tickets/showTicket/'.$issue->id;
            $result[] = $issueData;
        }

        return new PagedResult($result, $startAt, count($issues), count($issues));
    }

    private function convertStatusToEnum(string $statusKey, \stdClass $projectTicketStatusSettings): IssueStatusEnum
    {
        $statusType = $projectTicketStatusSettings->{$statusKey}->statusType;

        if (array_key_exists($statusType, self::STATUS_MAPPING)) {
            return self::STATUS_MAPPING[$statusType];
        }

        // Default fallback for unmatched statuses
        return IssueStatusEnum::OTHER;
    }

    private function getLeanDateTime(string $s): ?\DateTime
    {
        try {
            $date = new \DateTime($s, new \DateTimeZone('UTC'));
        } catch (\Exception) {
        }

        return isset($date) && ($date->getTimestamp() > 0) ? $date : null;
    }

    public function getProjectDataCollection(): ProjectDataCollection
    {
        $projectDataCollection = new ProjectDataCollection();
        $projects = $this->getAllProjects();

        foreach ($projects as $project) {
            $projectData = new ProjectData();
            $projectData->name = $project->name;
            $projectData->projectTrackerId = $project->id;
            // Leantime does not have a key for each project.
            $projectData->projectTrackerKey = $project->id;
            $projectData->projectTrackerProjectUrl = $this->leantimeUrl.'#/tickets/showTicket/'.$project->id;

            $projectVersions = $this->getSprintReportVersions($project->id);
            foreach ($projectVersions as $projectVersion) {
                $projectData->versions?->add($projectVersion);
            }

            $projectDataCollection->projectData->add($projectData);
        }

        return $projectDataCollection;
    }

    public function getClientDataForProject(string $projectId): array
    {
        throw new ApiServiceException('Method not implemented', 501);
    }

    /**
     * Get project.
     *
     * @param $key
     *   A project key or id
     */
    public function getProject($key): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getProject', ['id' => $key]);
    }

    public function getAllAccountData(): array
    {
        return [];
    }

    public function getSprintReportVersions(string $projectId): SprintReportVersions
    {
        $sprintReportVersions = new SprintReportVersions();
        $projectVersions = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAllMilestones', ['searchCriteria' => ['currentProject' => $projectId, 'type' => 'milestone']]);

        foreach ($projectVersions as $projectVersion) {
            $sprintReportVersion = new SprintReportVersion();
            $sprintReportVersion->id = $projectVersion->id;
            $sprintReportVersion->name = $projectVersion->headline;
            $sprintReportVersion->projectTrackerId = $projectVersion->projectId;

            $sprintReportVersions->versions->add($sprintReportVersion);
        }

        return $sprintReportVersions;
    }

    public function getWorklogDataCollection(string $projectId): WorklogDataCollection
    {
        $worklogDataCollection = new WorklogDataCollection();
        $worklogs = $this->getProjectWorklogs($projectId);

        $workersData = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.users.getAll');

        $workers = array_reduce($workersData, function ($carry, $item) {
            $carry[$item->id] = $item->username;

            return $carry;
        }, []);

        // Filter out all worklogs that do not belong to the project.
        // TODO: Remove filter when worklogs are filtered correctly by projectId in the API.
        $worklogs = array_filter($worklogs, fn ($worklog) => $worklog->projectId == $projectId);

        foreach ($worklogs as $worklog) {
            $worklogData = new WorklogData();
            if (isset($worklog->ticketId)) {
                $worklogData->projectTrackerId = $worklog->id;
                $worklogData->comment = $worklog->description ?? '';
                $worklogData->worker = $workers[$worklog->userId] ?? $worklog->userId;
                $worklogData->timeSpentSeconds = (int) ($worklog->hours * 60 * 60);
                $worklogData->started = new \DateTime($worklog->workDate, self::getLeantimeTimeZone());
                $worklogData->projectTrackerIsBilled = false;
                $worklogData->projectTrackerIssueId = $worklog->ticketId;
                $worklogData->kind = $worklog->kind;

                $worklogDataCollection->worklogData->add($worklogData);
            }
        }

        return $worklogDataCollection;
    }

    private function getTimesheets(array $params): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.timesheets.getAll', $params + [
            // The datatime format must match the internal Leantime date format
            // (cf. https://github.com/Leantime/leantime/blob/master/app/Core/Support/DateTimeHelper.php#L53)
            'dateFrom' => '2000-01-01 00:00:00',
            'dateTo' => '3000-01-01 00:00:00',
            'invEmpl' => '-1',
            'invComp' => '-1',
            'paid' => '-1',
        ]);
    }

    /**
     * Get from Leantime.
     *
     * @throws ApiServiceException
     */
    private function request(string $path, string $type, string $method, array $params = []): mixed
    {
        try {
            $response = $this->leantimeProjectTrackerApi->request($type, $path,
                ['json' => [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'id' => (new Ulid())->jsonSerialize(),
                    'params' => $params,
                ]]
            );

            $body = $response->getContent();

            if ($body) {
                $data = json_decode($body, null, 512, JSON_THROW_ON_ERROR);

                if (isset($data->error)) {
                    $message = $data->error->message;
                    if (isset($data->error->data)) {
                        $message .= ': '.(is_scalar($data->error->data) ? $data->error->data : json_encode($data->error->data));
                    }
                    throw new ApiServiceException($message, $data->error->code);
                }

                return $data->result;
            }
        } catch (\Throwable $e) {
            throw new ApiServiceException('Error from Leantime API: '.$e->getMessage(), (int) $e->getCode(), $e);
        }

        return null;
    }

    private function getLeantimeTimeZone(): \DateTimeZone
    {
        if (null === self::$leantimeTimeZone) {
            self::$leantimeTimeZone = new \DateTimeZone(self::LEANTIME_TIMEZONE);
        }

        return self::$leantimeTimeZone;
    }
}
