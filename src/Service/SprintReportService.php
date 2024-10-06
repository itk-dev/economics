<?php

namespace App\Service;

use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\ProjectVersionBudget;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\IssueStatusEnum;
use App\Model\SprintReport\SprintReportData;
use App\Model\SprintReport\SprintReportEpic;
use App\Model\SprintReport\SprintReportIssue;
use App\Model\SprintReport\SprintReportSprint;
use App\Model\SprintReport\SprintStateEnum;
use App\Repository\IssueRepository;
use App\Repository\ProjectVersionBudgetRepository;
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
        $noEpicEpic = new SprintReportEpic(self::NO_EPIC, $this->translator->trans('sprint_report.no_epic'));
        $sprintReportEpics->set(self::NO_EPIC, $noEpicEpic);
        $noSprintSprint = new SprintReportSprint(self::NO_SPRINT, $this->translator->trans('sprint_report.no_sprint'), SprintStateEnum::OTHER);
        $sprintReportSprints->set(self::NO_SPRINT, $noSprintSprint);
        $nowSprintId = $this->mapDateToSprintId(new \DateTime());

        $issues = $this->issueRepository->getIssuesByProjectAndVersion($project, $version);

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            $sprintReportIssue = new SprintReportIssue();
            $sprintReportIssues->add($sprintReportIssue);
            $epicKey = $issue->getEpicKey();

            if (!empty($epicKey)) {
                if (!$sprintReportEpics->containsKey($epicKey)) {
                    $epic = new SprintReportEpic($epicKey, $issue->getEpicName() ?? $epicKey);
                    $sprintReportEpics->set($epicKey, $epic);
                } else {
                    $epic = $sprintReportEpics->get($epicKey);
                }
            } else {
                $epic = $noEpicEpic;
            }

            $sprintReportIssue->epic = $epic;

            $dueDate = $issue->getDueDate();

            $sprintReportSprint = null;

            if (null !== $dueDate) {
                $sprintName = $this->mapDateToSprintName($dueDate);
                $sprintId = $this->mapDateToSprintId($dueDate);

                if (!$sprintReportSprints->containsKey($sprintName)) {
                    if ($sprintId > $nowSprintId) {
                        $sprintState = SprintStateEnum::FUTURE;
                    } elseif ($sprintId == $nowSprintId) {
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
            }

            if (null == $sprintReportSprint) {
                $sprintReportSprint = $noSprintSprint;
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
                    return null !== $workLogStarted && $sprintEntry->id == $this->mapDateToSprintId($workLogStarted);
                });

                $worklogSprintId = self::NO_SPRINT;

                if (!empty($worklogSprints)) {
                    $worklogSprintId = $worklogSprints[array_key_first($worklogSprints)]->id;
                }

                $newLoggedWork = (float) ($sprintReportIssue->epic->loggedWork->containsKey($worklogSprintId)
                        ? $sprintReportIssue->epic->loggedWork->get($worklogSprintId)
                        : 0) + $worklogSpentSeconds;
                $sprintReportIssue->epic->loggedWork->set($worklogSprintId, $newLoggedWork);
            }

            // Accumulate spentSum.
            $sprintReportIssue->epic->spentSum += $issueSpentSeconds;

            // Accumulate remainingSum.
            if (IssueStatusEnum::DONE !== $issue->getStatus()) {
                $remainingEstimateSeconds = (($issue->getHoursRemaining() ?? 0) * self::SECONDS_IN_HOUR);
                $sprintReportIssue->epic->remainingSum += $remainingEstimateSeconds;

                $issue->getPlanHours();

                $assignedToSprintId = $sprintReportIssue->assignedToSprint->id;
                $newRemainingWork = (float) ($sprintReportIssue->epic->remainingWork->containsKey($assignedToSprintId)
                        ? $sprintReportIssue->epic->remainingWork->get($assignedToSprintId)
                        : 0) + $remainingEstimateSeconds;
                $sprintReportIssue->epic->remainingWork->set($assignedToSprintId, $newRemainingWork);
            }

            // Accumulate originalEstimateSum.
            if (null !== $issue->getPlanHours()) {
                $sprintReportIssue->epic->originalEstimateSum += ($issue->getPlanHours() * self::SECONDS_IN_HOUR);
            }
        }

        // Sort sprints by key.
        try {
            $arr = $sprintReportSprints->toArray();
            uasort($arr, function ($a, $b) {
                return mb_strtolower($a->id) <=> mb_strtolower($b->id);
            });
            $sprintReportSprints = new ArrayCollection($arr);
        } catch (\Exception) {
            // Ignore. Results will not be sorted.
        }

        // Sort epics by name.
        try {
            $arr = $sprintReportEpics->toArray();
            uasort($arr, function ($a, $b) {
                return mb_strtolower($a->name) <=> mb_strtolower($b->name);
            });
            $sprintReportEpics = new ArrayCollection($arr);
        } catch (\Exception) {
            // Ignore. Results will not be sorted.
        }

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

        $sprintReportData->projectName = $project->getName() ?? 'No project name';
        $sprintReportData->versionName = $version->getName() ?? 'No version name';
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

    private function mapDateToSprintName(\DateTimeInterface $date): string
    {
        return $date->format('M Y');
    }

    private function mapDateToSprintId(\DateTimeInterface $date): string
    {
        return $date->format('Y.m');
    }
}
