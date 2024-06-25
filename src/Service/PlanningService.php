<?php

namespace App\Service;

use App\Model\Planning\Assignee;
use App\Model\Planning\AssigneeProject;
use App\Model\Planning\Issue;
use App\Model\Planning\PlanningData;
use App\Model\Planning\Project;
use App\Model\Planning\SprintSum;
use App\Model\Planning\Weeks;
use App\Repository\IssueRepository;
use Doctrine\Common\Collections\ArrayCollection;

class PlanningService
{
    public function __construct(
        private readonly DateTimeHelper $dateTimeHelper,
        private readonly IssueRepository $issueRepository,
    ) {
    }

    private function buildPlanningWeeks(PlanningData $planning): ArrayCollection
    {
        $weeks = $planning->weeks;

        $currentYear = (int) (new \DateTime())->format('Y');
        $currentWeek = (int) (new \DateTime())->format('W');

        $weeksOfYear = $this->dateTimeHelper->getWeeksOfYear($currentYear);
        foreach ($weeksOfYear as $week) {
            $firstAndLastDateOfWeek = $this->dateTimeHelper->getFirstAndLastDateOfWeek($week);
            $weekIsSupport = 1 === $week % 4;

            if ($weekIsSupport) {
                $supportWeek = new Weeks();
                $supportWeek->weekCollection->add($week);
                $supportWeek->weeks = 1;
                $supportWeek->weekGoalLow = 25;
                $supportWeek->weekGoalHigh = 34.5;
                $supportWeek->displayName = (string) $week;
                $supportWeek->dateSpan = $firstAndLastDateOfWeek['first'].' - '.$firstAndLastDateOfWeek['last'];
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
                        $regularWeek->dateSpan .= ' - '.$firstAndLastDateOfWeek['last'];
                        $weeks->add($regularWeek);
                        unset($regularWeek);
                    }
                } else {
                    $regularWeek = new Weeks();
                    $regularWeek->weekCollection->add($week);
                    $regularWeek->weeks = 1;
                    $regularWeek->weekGoalLow = 25 * 3;
                    $regularWeek->weekGoalHigh = 34.5 * 3;
                    $regularWeek->displayName = (string) $week;
                    $regularWeek->dateSpan = $firstAndLastDateOfWeek['first'];
                    if ($week == $currentWeek) {
                        $regularWeek->activeSprint = true;
                    }
                }
            }
        }
        return $weeks;
    }
    public function getPlanningData(): PlanningData
    {
        $planning = new PlanningData();
        $planning->weeks = $this->buildPlanningWeeks($planning);
        $assignees = $planning->assignees;
        $projects = $planning->projects;

        $weekIssues = [];
        $allIssues = $this->issueRepository->findAll();

        foreach ($allIssues as $issue) {
            $issueYear = $issue->getDueDate();
            $issueYear = $issueYear->format('Y');

            $issueWeek = $issue->getDueDate();
            $issueWeek = (int) $issueWeek->format('W');

            if ('-0001' !== $issueYear) {
                $weekIssues[$issueWeek][] = $issue;
            } else {
                $weekIssues['unscheduled'][] = $issue;
            }
        }

        foreach ($weekIssues as $week => $issues) {
            foreach ($issues as $issueData) {
                if ('0' !== $issueData->getStatus()) { // excludes done issues.
                    $week = (string) $week;
                    $issueProject = $issueData->getProject();
                    $projectKey = (string) $issueProject->getProjectTrackerId();
                    $projectDisplayName = $issueProject->getName();

                    $hoursRemaining = $issueData->getHoursRemaining();
                    if (empty($issueData->getWorker())) {
                        $assigneeKey = 'unassigned';
                        $assigneeDisplayName = 'Unassigned';
                    } else {
                        $assigneeKey = (string) $issueData->getWorker();
                        $assigneeDisplayName = $assigneeKey;
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
                            $issueData->getId(),
                            $issueData->getName(),
                            $hoursRemaining ?? null,
                            '/tickets/showKanban?showTicketModal='.$issueData->getId().'#/tickets/showTicket/'.$issueData->getId(),
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
                        $issueData->getId(),
                        $issueData->getName(),
                        isset($issueData->hourRemaining) ? $hoursRemaining : null,
                        '/tickets/showKanban?showTicketModal='.$issueData->getId().'#/tickets/showTicket/'.$issueData->getId(),
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
}
