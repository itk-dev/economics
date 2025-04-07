<?php

namespace App\Service;

use App\Entity\Issue as IssueEntity;
use App\Entity\WorkerGroup;
use App\Enum\IssueStatusEnum;
use App\Model\Planning\Assignee;
use App\Model\Planning\AssigneeProject;
use App\Model\Planning\Issue;
use App\Model\Planning\PlanningData;
use App\Model\Planning\Project;
use App\Model\Planning\SprintSum;
use App\Model\Planning\Weeks;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorkerRepository;
use Doctrine\Common\Collections\ArrayCollection;

class PlanningService
{
    public function __construct(
        private readonly DateTimeHelper $dateTimeHelper,
        private readonly IssueRepository $issueRepository,
        protected readonly float $weekGoalLow,
        protected readonly float $weekGoalHigh, private readonly WorkerRepository $workerRepository, private readonly ProjectRepository $projectRepository,
    ) {
    }

    private const UNNAMED_STR = 'unnamed';
    private const WEEKS_IN_SPRINT_PERIOD = 3;
    private const WEEKS_IN_SUPPORT_PERIOD = 1;

    /**
     * Retrieves the planning data containing the weeks, issues, assignees, and projects.
     *
     * @return PlanningData The planning data object
     *
     * @throws \Exception
     */
    public function getPlanningData(int $selectedYear, ?WorkerGroup $group, bool $holidayPlanning = false): PlanningData
    {
        $planning = new PlanningData();
        $planning->weeks = $this->buildPlanningWeeks($planning, $selectedYear, $holidayPlanning);

        $thisYear = (new \DateTime('first day of January '.$selectedYear))->setTime(0, 0)->format('Y-m-d H:i:s');
        $nextYear = (new \DateTime('first day of January '.($selectedYear + 1)))->setTime(0, 0)->format('Y-m-d H:i:s');

        $projects = null;

        if ($holidayPlanning) {
            $projects = $this->projectRepository->findBy(['holidayPlanning' => true]);
        }

        $allIssues = $this->issueRepository->findIssuesInDateRange($thisYear, $nextYear, $group, $projects);
        $sortedIssues = $this->sortIssuesByWeek($allIssues);

        foreach ($sortedIssues as $week => $issues) {
            $this->processIssuesForWeek($planning, $week, $issues, $holidayPlanning);
        }

        $planning->assignees = $this->sortAssigneeCollectionByDisplayName($planning->assignees);
        $planning->projects = $this->sortProjectCollectionByDisplayName($planning->projects);

        return $planning;
    }

    /**
     * Builds and returns an ArrayCollection of Weeks objects based on the given PlanningData object.
     *
     * @param PlanningData $planning The PlanningData object containing the weeks data
     *
     * @return ArrayCollection<string, Weeks> The ArrayCollection of Weeks objects representing the weeks in the planning
     */
    private function buildPlanningWeeks(PlanningData $planning, int $year, bool $planInSprints = true): ArrayCollection
    {
        $weeks = $planning->weeks;

        $now = new \DateTime();
        $currentWeek = ($year !== (int) $now->format('Y')) ? null : (int) $now->format('W');

        $weeksOfYear = $this->dateTimeHelper->getWeeksOfYear($year);
        foreach ($weeksOfYear as $week) {
            $weekIsSupport = $planInSprints || (1 === $week % (self::WEEKS_IN_SUPPORT_PERIOD + self::WEEKS_IN_SPRINT_PERIOD));

            if ($weekIsSupport) {
                $supportPeriod = new Weeks();
                $supportPeriod->weekCollection->add($week);
                $supportPeriod->weeks = self::WEEKS_IN_SUPPORT_PERIOD;
                $supportPeriod->weekGoalLow = $this->weekGoalLow;
                $supportPeriod->weekGoalHigh = $this->weekGoalHigh;
                $supportPeriod->displayName = (string) $week;
                if ($week == $currentWeek) {
                    $supportPeriod->activeSprint = true;
                }
                $weeks->add($supportPeriod);
            } else {
                if (isset($sprintPeriod)) {
                    $sprintPeriod->weekCollection->add($week);
                    ++$sprintPeriod->weeks;
                    $sprintPeriod->displayName .= '-'.$week;
                    if ($week == $currentWeek) {
                        $sprintPeriod->activeSprint = true;
                    }

                    if (self::WEEKS_IN_SPRINT_PERIOD === count($sprintPeriod->weekCollection)) {
                        $weeks->add($sprintPeriod);
                        unset($sprintPeriod);
                    }
                } else {
                    $sprintPeriod = new Weeks();
                    $sprintPeriod->weekCollection->add($week);
                    $sprintPeriod->weeks = 1;
                    $sprintPeriod->weekGoalLow = $this->weekGoalLow * self::WEEKS_IN_SPRINT_PERIOD;
                    $sprintPeriod->weekGoalHigh = $this->weekGoalHigh * self::WEEKS_IN_SPRINT_PERIOD;
                    $sprintPeriod->displayName = (string) $week;
                    if ($week == $currentWeek) {
                        $sprintPeriod->activeSprint = true;
                    }
                }
            }
        }

        return $weeks;
    }

    /**
     * Sorts issues by week.
     *
     * @param array $allIssues the array of all issues to sort
     *
     * @return array the sorted array of issues
     */
    private function sortIssuesByWeek(array $allIssues): array
    {
        $weekIssues = [];

        foreach ($allIssues as $issue) {
            $issueDueDate = $issue->getDueDate();

            if (!$issueDueDate) {
                continue;
            }

            $issueYear = $issueDueDate->format('Y');
            $issueWeek = (int) $issueDueDate->format('W');

            if ($issueYear) {
                $weekIssues[$issueWeek][] = $issue;
            } else {
                $weekIssues['unscheduled'][] = $issue;
            }
        }

        return $weekIssues;
    }

    private function processIssuesForWeek(PlanningData $planning, int $week, array $issues, bool $holidayPlanning = null): void
    {
        foreach ($issues as $issueData) {
            if (!$holidayPlanning && IssueStatusEnum::DONE === $issueData->getStatus()) {
                continue;
            }
            $week = (string)$week;
            $issueProject = $issueData->getProject();
            if (!$issueProject) {
                continue;
            }
            $projectKey = (string)$issueProject->getProjectTrackerId();
            $projectDisplayName = $issueProject->getName() ?? self::UNNAMED_STR;
            $hoursRemaining = $issueData->getHoursRemaining($issueData);
            $assigneeData = $this->getAssigneeData($issueData);

            $assignee = $this->getOrCreateAssignee($planning->assignees, $assigneeData);
            $sprintSum = $this->getOrCreateSprintSum($assignee->sprintSums, $week);
            $sprintSum->sumHours += $hoursRemaining;

            $assigneeProject = $this->getOrCreateAssigneeProject($assignee->projects, $projectKey, $projectDisplayName);
            $projectSprintSum = $this->getOrCreateSprintSum($assigneeProject->sprintSums, $week);
            $projectSprintSum->sumHours += $hoursRemaining;

            $assigneeProject->issues->add(
                new Issue(
                    (string)$issueData->getId(),
                    $issueData->getName() ?? self::UNNAMED_STR,
                    $hoursRemaining ?? null,
                    $issueData->getLinkToIssue(),
                    $week
                )
            );

            $project = $this->getOrCreateProject($planning->projects, $projectKey, $projectDisplayName);
// Add sprint sum if not already added.
            $projectSum = $this->getOrCreateSprintSum($project->sprintSums, $week);
            $projectSum->sumHours += $hoursRemaining;

            $projectAssignee = $this->getOrCreateAssigneeProject($project->assignees, $projectKey, $projectDisplayName);
            $projectAssigneeSprintSum = $this->getOrCreateSprintSum($projectAssignee->sprintSums, $week);
            $projectAssigneeSprintSum->sumHours += $hoursRemaining;

            $projectAssignee->issues->add(new Issue(
                (string)$issueData->getId(),
                $issueData->getName() ?? 'unnamed',
                isset($issueData->hourRemaining) ? $hoursRemaining : null,
                $issueData->getLinkToIssue(),
                $week
            ));
    }
}
    /**
     * Get the assignee key and display name.
     *
     * @param  IssueEntity $issue
     *
     * @return array
     */
    private function getAssigneeData(IssueEntity $issue): array
    {
        if (empty($issue->getWorker())) {
            return [
                'key' => 'unassigned',
                'displayName' => 'Unassigned',
                'weekNorm' => 37,
            ];
        } else {
            $assigneeKey = (string) $issue->getWorker();
            $assigneeName = $assigneeKey;

            $worker = $this->workerRepository->findOneBy(['email' => $assigneeKey]);

            if (null !== $worker && null !== $worker->getName()) {
                $assigneeName = $worker->getName();
            }

            return [
                'key' => $assigneeKey,
                'displayName' => $assigneeName,
                'weekNorm' => $worker?->getWorkload() ?? 37,
            ];
        }
    }

    /**
     * Gets or creates an Assignee object in an ArrayCollection.
     *
     * @param ArrayCollection<string, Assignee> $assignees the ArrayCollection containing the Assignee objects
     * @param array $assigneeData
     *
     * @return Assignee the retrieved or created Assignee object
     */
    private function getOrCreateAssignee(ArrayCollection $assignees, array $assigneeData): Assignee
    {
        if (!$assignees->containsKey($assigneeData['key'])) {
            $assignees->set($assigneeData['key'], new Assignee($assigneeData['key'], $assigneeData['displayName'], $assigneeData['weekNorm']));
        }

        return $assignees->get($assigneeData['key']) ?? throw new \RuntimeException("Assignee key {$assigneeData['key']} does not exist");
    }

    /**
     * Gets or creates a SprintSum object in an ArrayCollection.
     *
     * @param ArrayCollection<string, SprintSum> $sprintSums The ArrayCollection containing SprintSum objects
     * @param string $week The week for which SprintSum object needs to be fetched or created
     *
     * @return SprintSum The SprintSum object corresponding to the given $week
     */
    private function getOrCreateSprintSum(ArrayCollection $sprintSums, string $week): SprintSum
    {
        if (!$sprintSums->containsKey($week)) {
            $sprintSums->set($week, new SprintSum($week));
        }

        return $sprintSums->get($week) ?? throw new \RuntimeException("Sprint sum for week {$week} does not exist");
    }

    /**
     * Gets or creates a Project object in an ArrayCollection.
     *
     * @param ArrayCollection<string, Project> $projects the ArrayCollection containing the Project objects
     * @param string $projectKey the key of the Project object
     * @param string $projectName the name of the Project object
     *
     * @return Project the retrieved or created Project object
     */
    private function getOrCreateProject(ArrayCollection $projects, string $projectKey, string $projectName): Project
    {
        if (!$projects->containsKey($projectKey)) {
            $projects->set($projectKey, new Project(
                $projectKey,
                $projectName));
        }

        return $projects->get($projectKey) ?? throw new \RuntimeException("Project with key {$projectKey} does not exist");
    }

    /**
     * Gets or creates an AssigneeProject object in an ArrayCollection.
     *
     * @param ArrayCollection<string, AssigneeProject> $projects The ArrayCollection containing AssigneeProject objects
     * @param string $projectKey The key for the AssigneeProject object that needs to be fetched or created
     * @param string $projectName The name for the AssigneeProject object that needs to be fetched or created
     *
     * @return AssigneeProject The AssigneeProject object corresponding to the given $projectKey
     */
    private function getOrCreateAssigneeProject(ArrayCollection $projects, string $projectKey, string $projectName): AssigneeProject
    {
        if (!$projects->containsKey($projectKey)) {
            $assigneeProject = new AssigneeProject($projectKey, $projectName);
            $projects->set($projectKey, $assigneeProject);
        }

        return $projects->get($projectKey) ?? throw new \RuntimeException("Assignee project with key {$projectKey} does not exist");
    }

    /**
     * Sorts an Assignee collection by the displayName property of its elements.
     *
     * @param ArrayCollection<string, Assignee> $collection The ArrayCollection to be sorted
     *
     * @return ArrayCollection<string, Assignee> A new ArrayCollection with the sorted elements
     */
    private function sortAssigneeCollectionByDisplayName(ArrayCollection $collection): ArrayCollection
    {
        /** @var \ArrayIterator $iterator */
        $iterator = $collection->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->displayName) <=> mb_strtolower($b->displayName);
        });

        return new ArrayCollection(iterator_to_array($iterator));
    }

    /**
     * Sorts a Project ArrayCollection by the displayName property of its elements.
     *
     * @param ArrayCollection<string, Project> $collection The ArrayCollection to be sorted
     *
     * @return ArrayCollection<string, Project> A new ArrayCollection with the sorted elements
     */
    private function sortProjectCollectionByDisplayName(ArrayCollection $collection): ArrayCollection
    {
        /** @var \ArrayIterator $iterator */
        $iterator = $collection->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->displayName) <=> mb_strtolower($b->displayName);
        });

        return new ArrayCollection(iterator_to_array($iterator));
    }
}
