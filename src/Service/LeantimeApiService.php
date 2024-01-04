<?php

namespace App\Service;

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
use App\Model\SprintReport\SprintReportProject;
use App\Model\SprintReport\SprintReportProjects;
use App\Model\SprintReport\SprintReportSprint;
use App\Model\SprintReport\SprintReportVersion;
use App\Model\SprintReport\SprintReportVersions;
use App\Model\SprintReport\SprintStateEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LeantimeApiService implements ProjectTrackerInterface
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
     * @throws ApiServiceException
     */
    public function getAllProjects(): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getAll', []);
    }

    /**
     * Get all projects, including archived.
     *
     * @return SprintReportProjects array of SprintReportProjects
     *
     * @throws ApiServiceException
     */
    public function getAllProjectsV2(): SprintReportProjects
    {
        $sprintReportProjects = new SprintReportProjects();
        $projects = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getAll', []);

        foreach ($projects as $project) {
            $sprintReportProjects->projects->add(
                new SprintReportProject(
                    $project->id,
                    $project->name
                )
            );
        }

        return $sprintReportProjects;
    }

    /**
     * Get projectV2.
     *
     * @param $key
     *   A project key or id
     *
     * @return SprintReportProject SprintReportProject
     *
     * @throws ApiServiceException
     */
    public function getProjectV2(string $projectId): SprintReportProject
    {
        $project = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.projects.getProject', ['id' => $projectId]);

        return new SprintReportProject(
            $project->id,
            $project->name
        );
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

    public function getProjectVersions(string $projectId): SprintReportVersions
    {
        $sprintReportVersions = new SprintReportVersions();
        $projectVersions = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAllMilestones', ['searchCriteria' => ['currentProject' => $projectId, 'type' => 'milestone']]);

        foreach ($projectVersions as $projectVersion) {
            $sprintReportVersions->versions->add(
                new SprintReportVersion(
                    $projectVersion->id,
                    $projectVersion->headline
                )
            );
        }

        return $sprintReportVersions;
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
        $result = $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.tickets.getAll', [
            'searchCriteria' => [
                'sprint' => $sprintId,
            ],
        ]);

        return $result;
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
                null,
            );
        } else {
            throw new ApiServiceException('Sprint not found', 404);
        }
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
                            $this->leantimeUrl.'/tickets/showTicket/'.$issueData->id,
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
                        $this->leantimeUrl.'/tickets/showTicket/'.$issueData->id,
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

    public function getTimesheetsForTicket($ticketId): mixed
    {
        return $this->request(self::API_PATH_JSONRPC, 'POST', 'leantime.rpc.timesheets.getAll', ['invEmpl' => '-1', 'invComp' => '-1', 'paid' => '-1', 'ticketFilter' => $ticketId]);
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
     */
    private function request(string $path, string $type, string $method, array $params = []): mixed
    {
        try {
            $response = $this->leantimeProjectTrackerApi->request($type, $path,
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
