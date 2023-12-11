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
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LeantimeApiService implements LeantimeApiServiceInterface
{
    private const API_PATH_JSONRPC = '/api/jsonrpc/';

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
}
