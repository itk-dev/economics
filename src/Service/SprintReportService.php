<?php

namespace App\Service;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\ProjectVersionBudget;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\IssueStatusEnum;
use App\Exception\ApiServiceException;
use App\Model\SprintReport\SprintReportData;
use App\Model\SprintReport\SprintReportEpic;
use App\Model\SprintReport\SprintReportIssue;
use App\Model\SprintReport\SprintReportSprint;
use App\Model\SprintReport\SprintStateEnum;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\ProjectVersionBudgetRepository;
use App\Repository\VersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SprintReportService
{
    private const NO_SPRINT = 'NO_SPRINT';
    private const NO_EPIC = 'NO_EPIC';
    private const SECONDS_IN_HOUR = 3600;

    public function __construct(
        private readonly ProjectVersionBudgetRepository $budgetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly IssueRepository $issueRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getSprintReportData(Project $project, Version $version): SprintReportData
    {
        $sprintReportData = new SprintReportData();
        $sprintReportEpics = $sprintReportData->epics;
        $sprintReportIssues = $sprintReportData->issues;
        $sprintReportSprints = $sprintReportData->sprints;

        // TODO: Translations.
        $sprintReportEpics->set(self::NO_EPIC, new SprintReportEpic(self::NO_EPIC, $this->translator->trans('sprint_report.no_epic')));
        $sprintReportSprints->set(self::NO_SPRINT, new SprintReportSprint(self::NO_SPRINT, $this->translator->trans('sprint_report.no_sprint'), SprintStateEnum::OTHER));

        $issues = $this->issueRepository->getIssuesByProjectAndVersion($project, $version);

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            $sprintReportIssue = new SprintReportIssue();
            $sprintReportIssues->add($sprintReportIssue);

            if (!empty($issue->getEpicKey())) {
                $epic = new SprintReportEpic($issue->getEpicKey(), $issue->getEpicName());
                $sprintReportEpics->set($issue->getEpicKey(), $epic);
            } else {
                $epic = $sprintReportEpics->get(self::NO_EPIC);
            }

            $sprintReportIssue->epic = $epic;

            $dueDate = $issue->getDueDate();

            if ($dueDate !== null) {
                $nowSprintId = $this->mapDateToSprintId(new \DateTime());
                $sprintName = $this->mapDateToSprintName($dueDate);
                $sprintId = $this->mapDateToSprintId($dueDate);

                if (!$sprintReportSprints->containsKey($sprintName)) {
                    if ($sprintId > $nowSprintId) {
                        $sprintState = SprintStateEnum::FUTURE;
                    } else if ($sprintId == $nowSprintId) {
                        $sprintState = SprintStateEnum::ACTIVE;
                    } else {
                        $sprintState = SprintStateEnum::PAST;
                    }

                    $sprintReportSprint = new SprintReportSprint(
                        $sprintId,
                        $sprintName,
                        $sprintState,
                    );

                    $sprintReportSprints->set($sprintName, $sprintReportSprint);
                } else {
                    $sprintReportSprint = $sprintReportSprints->get($sprintName);
                }
            } else {
                $sprintReportSprint = $sprintReportSprints->get(self::NO_SPRINT);
            }

            $sprintReportIssue->assignedToSprint = $sprintReportSprint;

            $worklogs = $issue->getWorklogs();

            $issueSpentSeconds = 0;

            /** @var Worklog $worklog */
            foreach ($worklogs as $worklog) {
                $workLogStarted = $worklog->getStarted();
                $worklogSpentSeconds = $worklog->getTimeSpentSeconds();
                $issueSpentSeconds += $worklogSpentSeconds;

                $worklogSprints = array_filter($sprintReportSprints->toArray(), function ($sprintEntry) use ($workLogStarted) {
                    /* @var SprintReportSprint $sprintEntry */
                    return $sprintEntry->id == $this->mapDateToSprintId($workLogStarted);
                });

                $worklogSprintId = self::NO_SPRINT;

                if (!empty($worklogSprints)) {
                    $worklogSprintId = $worklogSprints[array_key_first($worklogSprints)]->id;
                }

                $newLoggedWork = (float) ($sprintReportIssue->epic->loggedWork->containsKey($worklogSprintId) ? $sprintReportIssue->epic->loggedWork->get($worklogSprintId) : 0) + $worklogSpentSeconds;
                $sprintReportIssue->epic->loggedWork->set($worklogSprintId, $newLoggedWork);
            }

            // Accumulate spentSum.
            $sprintReportIssue->epic->spentSum += $issueSpentSeconds;

            // Accumulate remainingSum.
            if (IssueStatusEnum::DONE !== $issue->getStatus()) {
                $remainingEstimateSeconds = (($issue->getHoursRemaining() ?? 0) * self::SECONDS_IN_HOUR);
                $sprintReportIssue->epic->remainingSum += $remainingEstimateSeconds;

                $assignedToSprint = $sprintReportIssue->assignedToSprint;
                $newRemainingWork = (float) ($sprintReportIssue->epic->remainingWork->containsKey($assignedToSprint->id) ? $sprintReportIssue->epic->remainingWork->get($assignedToSprint->id) : 0) + $remainingEstimateSeconds;
                $sprintReportIssue->epic->remainingWork->set($assignedToSprint->id, $newRemainingWork);
                $sprintReportIssue->epic->plannedWorkSum += $remainingEstimateSeconds;
            }

            // Accumulate originalEstimateSum.
            if ($issue->getPlanHours() !== null) {
                $sprintReportIssue->epic->originalEstimateSum += ($issue->getPlanHours() * self::SECONDS_IN_HOUR);
            }
        }

        // Sort sprints by key.
        // @var \ArrayIterator $iterator
        $iterator = $sprintReportSprints->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->id) <=> mb_strtolower($b->id);
        });
        $sprintReportSprints = new ArrayCollection(iterator_to_array($iterator));

        // Sort epics by name.
        // @var \ArrayIterator $iterator
        $iterator = $sprintReportEpics->getIterator();
        $iterator->uasort(function ($a, $b) {
            return mb_strtolower($a->name) <=> mb_strtolower($b->name);
        });
        $sprintReportEpics = new ArrayCollection(iterator_to_array($iterator));

        $remainingSum = array_reduce($sprintReportEpics->toArray(), function ($carry, $epic) {
            return $carry + $epic->remainingSum;
        }, 0);

        $spentSum = array_reduce($sprintReportEpics->toArray(), function ($carry, $epic) {
            return $carry + $epic->spentSum;
        }, 0);

        $originalEstimateSum = array_reduce($sprintReportEpics->toArray(), function ($carry, $epic) {
            return $carry + $epic->originalEstimateSum;
        }, 0);

        // Calculate spent, remaining hours.
        $spentHours = $spentSum / self::SECONDS_IN_HOUR;
        $remainingHours = $remainingSum / self::SECONDS_IN_HOUR;

        $sprintReportData->projectName = $project->getName();
        $sprintReportData->versionName = $version->getName();
        $sprintReportData->remainingHours = $remainingHours;
        $sprintReportData->spentHours = $spentHours;
        $sprintReportData->spentSum = $spentSum;
        $sprintReportData->projectHours = $spentHours + $remainingHours;
        $sprintReportData->epics = $sprintReportEpics;
        $sprintReportData->sprints = $sprintReportSprints;
        $sprintReportData->originalEstimateSum = $originalEstimateSum;

        return $sprintReportData;
    }

    public function saveBudget($projectId, $versionId, $budgetAmount): ProjectVersionBudget
    {
        $budget = $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);

        if (!$budget) {
            $budget = new ProjectVersionBudget();
            $budget->setProjectId($projectId);
            $budget->setVersionId($versionId);

            $this->entityManager->persist($budget);
        }

        $budget->setBudget($budgetAmount);

        $this->entityManager->flush();

        return $budget;
    }

    public function getBudget($projectId, $versionId): ?ProjectVersionBudget
    {
        return $this->budgetRepository->findOneBy(['projectId' => $projectId, 'versionId' => $versionId]);
    }

    private function mapDateToSprintName(\DateTime $date): string
    {
        return $date->format('M Y');
    }

    private function mapDateToSprintId(\DateTime $date): string
    {
        return $date->format('Y.m');
    }
}
