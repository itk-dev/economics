<?php

namespace App\Service;

use App\Enum\IssueStatusEnum;
use App\Exception\ApiServiceException;
use App\Exception\EconomicsException;
use App\Interface\DataProviderServiceInterface;
use App\Model\Invoices\IssueData;
use App\Model\Invoices\PagedResult;
use App\Model\Invoices\ProjectData;
use App\Model\Invoices\ProjectDataCollection;
use App\Model\Invoices\VersionData;
use App\Model\Invoices\WorklogData;
use App\Model\Invoices\WorklogDataCollection;
use App\Model\Planning\Assignee;
use App\Model\Planning\AssigneeProject;
use App\Model\Planning\Issue;
use App\Model\Planning\PlanningData;
use App\Model\Planning\Project;
use App\Model\Planning\SprintSum;
use App\Model\Planning\Weeks;
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
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LeantimeApiService implements DataProviderServiceInterface
{
    private const API_PATH_JSONRPC = '/api/jsonrpc/';
    private const NO_SPRINT = 'NoSprint';
    private const PAST = 'PAST';
    private const PRESENT = 'PRESENT';
    private const FUTURE = 'FUTURE';

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
     * @throws ApiServiceException|EconomicsException
     */
    public function getAllProjects(): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getAll', []);
    }

    /**
     * Get all worklogs for project.
     *
     * @param $projectId
     *
     * @return mixed
     *
     * @throws ApiServiceException
     * @throws EconomicsException
     */
    public function getProjectWorklogs($projectId): mixed
    {
        return $this->getTimesheets([
            'id' => $projectId,
        ]);
    }

    /**
     * Get all projects, including archived.
     *
     * @return SprintReportProjects array of SprintReportProjects
     *
     * @throws ApiServiceException|EconomicsException
     */
    public function getSprintReportProjects(): SprintReportProjects
    {
        $sprintReportProjects = new SprintReportProjects();
        $projects = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getAll', []);

        foreach ($projects as $project) {
            $sprintReportProject = new SprintReportProject();
            $sprintReportProject->id = $project->id;
            $sprintReportProject->name = $project->name;

            $sprintReportProjects->projects->add($sprintReportProject);
        }

        return $sprintReportProjects;
    }

    /**
     * Get projectV2.
     *
     * @param string $projectId A project key or id
     *
     * @return SprintReportProject SprintReportProject
     *
     * @throws ApiServiceException|EconomicsException
     */
    public function getSprintReportProject(string $projectId): SprintReportProject
    {
        $project = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getProject', ['id' => $projectId]);

        $sprintReportProject = new SprintReportProject();
        $sprintReportProject->id = $project->id;
        $sprintReportProject->name = $project->name;

        return $sprintReportProject;
    }

    /**
     * @throws ApiServiceException
     * @throws EconomicsException
     */
    private function getProjectIssuesPaged($projectId, $startAt, $maxResults = 50): array
    {
        // TODO: Implement pagination.
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', ['searchCriteria' => ['currentProject' => $projectId]]);
    }

    /**
     * @throws ApiServiceException
     * @throws EconomicsException
     */
    public function getIssuesDataForProjectPaged(string $projectId, $startAt = 0, $maxResults = 50): PagedResult
    {
        $result = [];

        $issues = $this->getProjectIssuesPaged($projectId, $startAt, $maxResults);

        $workersData = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.users.getAll');

        $workers = array_reduce($workersData, function ($carry, $item) {
            $carry[$item->id] = $item->username;

            return $carry;
        }, []);

        foreach ($issues as $issue) {
            $issueData = new IssueData();

            $issueData->name = $issue->headline;
            $issueData->status = $this->convertStatusToEnum($issue->status);
            $issueData->projectTrackerId = $issue->id;
            // Leantime does not have a key for each issue.
            $issueData->projectTrackerKey = $issue->id;
            $issueData->accountId = '';
            $issueData->accountKey = '';
            $issueData->epicKey = $issue->tags;
            $issueData->epicName = $issue->tags;
            $issueData->planHours = $issue->planHours;
            $issueData->hourRemaining = $issue->hourRemaining;
            $issueData->dueDate = !empty($issue->dateToFinish) && '0000-00-00 00:00:00' !== $issue->dateToFinish ? new \DateTime($issue->dateToFinish) : null;
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

    private function convertStatusToEnum(string $statusNumber)
    {
        $statusNumber = (int) $statusNumber;
        $statusMapping = [
            -1 => IssueStatusEnum::ARCHIVED,
            0 => IssueStatusEnum::DONE,
            1 => IssueStatusEnum::BLOCKED,
            2 => IssueStatusEnum::WAITING,
            3 => IssueStatusEnum::NEW,
            4 => IssueStatusEnum::IN_PROGRESS,
        ];

        if (array_key_exists($statusNumber, $statusMapping)) {
            return $statusMapping[$statusNumber];
        }

        throw new \InvalidArgumentException('Invalid status number');
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
     *
     * @throws ApiServiceException
     */
    public function getProject($key): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getProject', ['id' => $key]);
    }

    public function getAllAccountData(): array
    {
        return [];
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
                $worklogData->started = new \DateTime($worklog->workDate);
                $worklogData->projectTrackerIsBilled = false;
                $worklogData->projectTrackerIssueId = $worklog->ticketId;

                $worklogDataCollection->worklogData->add($worklogData);
            }
        }

        return $worklogDataCollection;
    }

    /**
     * Get all sprints for a given board.
     *
     * @throws ApiServiceException
     */
    public function getAllSprints(): array
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.sprints.getAllSprints', ['projectId' => '6']);
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
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', ['searchCriteria' => ['sprint' => $sprintId]]);
    }

    /**
     * @throws ApiServiceException
     */
    private function getIssueSprint($issueEntry): SprintReportSprint
    {
        $sprint = false;

        if ((bool) $issueEntry->sprint) {
            $sprint = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.sprints.getSprint', ['id' => $issueEntry->sprint]);
        }

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
                null,
            );
        } else {
            throw new ApiServiceException('Sprint not found', 404);
        }
    }

    /**
     * @throws ApiServiceException
     */
    private function getAllIssues(): array
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', ['searchCriteria' => []]);
    }

    /**
     * Create data for planning page.
     *
     * @throws ApiServiceException
     * @throws \Exception
     */
    public function getPlanningDataWeeks(): PlanningData
    {
        $planning = new PlanningData();
        $assignees = $planning->assignees;
        $projects = $planning->projects;
        $weeks = $planning->weeks;

        $currentYear = (int) (new \DateTime())->format('Y');
        $currentWeek = (int) (new \DateTime())->format('W');

        // TODO: How to handle 53 weeks
        // A year can actually have 53 weeks (cf. https://en.wikipedia.org/wiki/ISO_week_date#Weeks_per_year), and the year 2026 (in the near future is one of them), so this should be rewritten to iterate over all weeks in the year (or use https://stackoverflow.com/a/21480444).
        for ($weekNumber = 1; $weekNumber <= 52; ++$weekNumber) {
            $date = (new \DateTime())->setISODate($currentYear, $weekNumber);
            $week = (int) $date->format('W'); // Cast as int to remove leading zero.
            $weekFirstDay = $date->setISODate($currentYear, $week, 1)->format('j/n');
            $weekLastDay = $date->setISODate($currentYear, $week, 5)->format('j/n');
            $weekIsSupport = 1 === $week % 4;

            if ($weekIsSupport) {
                $supportWeek = new Weeks();
                $supportWeek->weekCollection->add($week);
                $supportWeek->weeks = 1;
                $supportWeek->weekGoalLow = $this->weekGoalLow;
                $supportWeek->weekGoalHigh = $this->weekGoalHigh;
                $supportWeek->displayName = (string) $week;
                $supportWeek->dateSpan = $weekFirstDay.' - '.$weekLastDay;
                if ($week == $currentWeek) {
                    $supportWeek->activeSprint = true;
                }
                $weeks->add($supportWeek);
            } else {
                if (isset($regularWeek)) {
                    $regularWeek->weekCollection->add($week);
                    ++$regularWeek->weeks;
                    $regularWeek->displayName .= '-'.$week;
                    if ($week == $currentWeek) {
                        $regularWeek->activeSprint = true;
                    }

                    if (3 === count($regularWeek->weekCollection)) {
                        $regularWeek->dateSpan .= ' - '.$weekLastDay;
                        $weeks->add($regularWeek);
                        unset($regularWeek);
                    }
                } else {
                    $regularWeek = new Weeks();
                    $regularWeek->weekCollection->add($week);
                    $regularWeek->weeks = 1;
                    $regularWeek->weekGoalLow = $this->weekGoalLow * 3;
                    $regularWeek->weekGoalHigh = $this->weekGoalHigh * 3;
                    $regularWeek->displayName = (string) $week;
                    $regularWeek->dateSpan = $weekFirstDay;
                    if ($week == $currentWeek) {
                        $regularWeek->activeSprint = true;
                    }
                }
            }
        }

        $weekIssues = [];
        $allIssues = $this->getAllIssues();

        foreach ($allIssues as $issue) {
            $issueYear = new \DateTime($issue->dateToFinish);
            $issueYear = $issueYear->format('Y');

            $issueWeek = new \DateTime($issue->dateToFinish);
            $issueWeek = (int) $issueWeek->format('W');

            if ('-0001' !== $issueYear) {
                $weekIssues[$issueWeek][] = $issue;
            } else {
                $weekIssues['unscheduled'][] = $issue;
            }
        }

        foreach ($weekIssues as $week => $issues) {
            foreach ($issues as $issueData) {
                if ('0' !== $issueData->status) { // excludes done issues.
                    $week = (string) $week;
                    $projectKey = (string) $issueData->projectId;
                    $projectDisplayName = $issueData->projectName;

                    $hoursRemaining = $issueData->hourRemaining;
                    if (empty($issueData->editorId)) {
                        $assigneeKey = 'unassigned';
                        $assigneeDisplayName = 'Unassigned';
                    } else {
                        $assigneeKey = (string) $issueData->editorId;
                        if (isset($issueData->editorFirstname) || isset($issueData->editorLastname)) {
                            $assigneeDisplayName = $issueData->editorFirstname.' '.$issueData->editorLastname;
                        } else {
                            $assigneeDisplayName = 'Name missing';
                        }
                    }
                    // Add assignee if not already added.
                    if (!$assignees->containsKey($assigneeKey)) {
                        $assignees->set($assigneeKey, new Assignee($assigneeKey, $assigneeDisplayName));
                    }

                    /** @var Assignee $assignee */
                    $assignee = $assignees->get($assigneeKey);

                    // Add sprint if not already added.
                    if (!$assignee->sprintSums->containsKey($week)) {
                        $assignee->sprintSums->set($week, new SprintSum($week));
                    }

                    /** @var SprintSum $sprintSum */
                    $sprintSum = $assignee->sprintSums->get($week);
                    $sprintSum->sumHours += $hoursRemaining;

                    // Add assignee project if not already added.
                    if (!$assignee->projects->containsKey($projectKey)) {
                        $assigneeProject = new AssigneeProject($projectKey, $projectDisplayName);
                        $assignee->projects->set($projectKey, $assigneeProject);
                    }

                    /** @var AssigneeProject $assigneeProject */
                    $assigneeProject = $assignee->projects->get($projectKey);

                    // Add project sprint sum if not already added.
                    if (!$assigneeProject->sprintSums->containsKey($week)) {
                        $assigneeProject->sprintSums->set($week, new SprintSum($week));
                    }

                    /** @var SprintSum $projectSprintSum */
                    $projectSprintSum = $assigneeProject->sprintSums->get($week);
                    if (isset($projectSprintSum)) {
                        $projectSprintSum->sumHours += $hoursRemaining;
                    }

                    $assigneeProject->issues->add(
                        new Issue(
                            $issueData->id,
                            $issueData->headline,
                            isset($issueData->hourRemaining) ? $hoursRemaining : null,
                            $this->leantimeUrl.'/tickets/showKanban?showTicketModal='.$issueData->id.'#/tickets/showTicket/'.$issueData->id,
                            $week
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

                    if (!$project->sprintSums->containsKey($week)) {
                        $project->sprintSums->set($week, new SprintSum($week));
                    }

                    /** @var SprintSum $projectSprintSum */
                    $projectSprintSum = $project->sprintSums->get($week);
                    $projectSprintSum->sumHours += $hoursRemaining;

                    if (!$project->assignees->containsKey($assigneeKey)) {
                        $project->assignees->set($assigneeKey, new AssigneeProject(
                            $assigneeKey,
                            $assigneeDisplayName,
                        ));
                    }

                    /** @var AssigneeProject $projectAssignee */
                    $projectAssignee = $project->assignees->get($assigneeKey);

                    if (!$projectAssignee->sprintSums->containsKey($week)) {
                        $projectAssignee->sprintSums->set($week, new SprintSum($week));
                    }

                    /** @var SprintSum $projectAssigneeSprintSum */
                    $projectAssigneeSprintSum = $projectAssignee->sprintSums->get($week);
                    $projectAssigneeSprintSum->sumHours += $hoursRemaining;

                    $projectAssignee->issues->add(new Issue(
                        $issueData->id,
                        $issueData->headline,
                        isset($issueData->hourRemaining) ? $hoursRemaining : null,
                        $this->leantimeUrl.'/tickets/showKanban?showTicketModal='.$issueData->id.'#/tickets/showTicket/'.$issueData->id,
                        $week
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

    public function getTimesheetsForTicket(string $ticketId): mixed
    {
        return $this->getTimesheets([
            'ticketFilter' => $ticketId,
        ]);
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
     * @throws ApiServiceException
     */
    private function getIssuesForProjectMilestone($projectId, $milestoneId): array
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', ['searchCriteria' => ['currentProject' => $projectId, 'milestone' => $milestoneId]]);
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

        $epics->set('noEpic', new SprintReportEpic('noEpic', 'Uden Tag'));

        // Get version and project.
        $milestone = $this->getMilestone($versionId);
        $project = $this->getProject($projectId);

        $issueEntries = $this->getIssuesForProjectMilestone($projectId, $versionId);

        $issueCount = 1;
        foreach ($issueEntries as $issueEntry) {
            $issue = new SprintReportIssue();
            $issues->add($issue);

            /* Tags are stored as a comma seperated string.
            In our implementation of Leantime, tickets are only supposed to have one tag.
            Tickets with multiple tags will not break, but it would look wierd in the report. */
            if (isset($issueEntry->tags)) {
                $tag = new SprintReportEpic($issueEntry->tags, $issueEntry->tags);
                $epics->set($issueEntry->tags, $tag);
            } else {
                $tag = $epics->get('noEpic');
            }

            if (!$tag instanceof SprintReportEpic) {
                continue;
            }

            $issue->epic = $tag;

            // Get sprint for issue.
            if ((bool) $issueEntry->sprint) {
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
                $newLoggedWork = (float) ($issue->epic->loggedWork->containsKey($worklogSprintId) ? $issue->epic->loggedWork->get($worklogSprintId) : 0) + ($worklog->hours * 60 * 60);
                $issue->epic->loggedWork->set($worklogSprintId, $newLoggedWork);
            }

            // Accumulate spentSum.
            $spentSum += ($issueEntry->bookedHours * 60 * 60);
            $issue->epic->spentSum += ($issueEntry->bookedHours * 60 * 60);

            // Accumulate remainingSum.
            if ('0' !== $issueEntry->status && isset($issueEntry->hourRemaining)) {
                $remainingEstimateSeconds = ($issueEntry->hourRemaining * 60 * 60);
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
            if (isset($issueEntry->planHours)) {
                $issue->epic->originalEstimateSum += ($issueEntry->planHours * 60 * 60);

                $sprintReportData->originalEstimateSum += ($issueEntry->planHours * 60 * 60);
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
        $iterator = $epics->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->name) <=> mb_strtolower($b->name);
        });
        $epics = new ArrayCollection(iterator_to_array($iterator));
        // Calculate spent, remaining hours.
        $spentHours = $spentSum / 3600;
        $remainingHours = $remainingSum / 3600;

        $sprintReportData->projectName = $project->name;
        $sprintReportData->versionName = $milestone->headline;
        $sprintReportData->remainingHours = $remainingHours;
        $sprintReportData->spentHours = $spentHours;
        $sprintReportData->spentSum = $spentSum;
        $sprintReportData->projectHours = $spentHours + $remainingHours;
        $sprintReportData->epics = $epics;
        $sprintReportData->sprints = $sprints;

        return $sprintReportData;
    }

    /**
     * Get from Leantime.
     *
     * @throws ApiServiceException
     * @throws EconomicsException
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
}
