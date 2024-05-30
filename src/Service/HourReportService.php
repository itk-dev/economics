<?php

namespace App\Service;

use App\Exception\EconomicsException;
use App\Model\Reports\HourReportData;
use App\Model\Reports\HourReportProjectTag;
use App\Model\Reports\HourReportProjectTicket;
use App\Model\Reports\HourReportTimesheet;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\ProjectVersionBudgetRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;

class HourReportService
{
    public function __construct(
        private readonly ProjectVersionBudgetRepository $budgetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly IssueRepository $issueRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly VersionRepository $versionRepository,
    ) {
    }

    /**
     * @throws EconomicsException
     */
    public function getProjects(): array
    {
        $projects = $this->projectRepository->findAll();
        $projectChoices = [];
        foreach ($projects as $project) {
            $projectChoices[$project->getName()] = $project->getId();
        }

        return $projectChoices;
    }

    /**
     * @throws EconomicsException
     */
    public function getMilestones(string $projectId, bool $allAllOption = false): array
    {
        $milestones = $this->versionRepository->findBy(['project' => $projectId]);
        $milestoneChoices = [];
        if ($allAllOption) {
            $milestoneChoices['All milestones'] = '0';
        }
        foreach ($milestones as $milestone) {
            $milestoneChoices[$milestone->getName()] = $milestone->getId();
        }

        return $milestoneChoices;
    }

    /**
     * @throws EconomicsException
     */
    public function getHourReport(string $projectId, int $versionId = null): HourReportData
    {
        if (!$projectId) {
            throw new EconomicsException('No project id specified');
        }
        $hourReportData = new HourReportData(0, 0);
        $projectIssues = $this->issueRepository->findBy(['project' => $projectId]);

        foreach ($projectIssues as $issue) {
            // If version is provided, we only want the issues containing the versionId
            if ($versionId) {
                $issueHasVersion = $this->issueRepository->issueContainsVersion($issue->getId(), $versionId);

                if (!$issueHasVersion) {
                    continue;
                }
            }
            $totalTicketEstimated = (float) $issue->planHours;
            $timesheetData = $this->worklogRepository->findBy(['issue' => $issue->getId()]);
            list($timesheets, $totalTicketSpent) = $this->processTimesheetsData($timesheetData);

            $projectTicket = new HourReportProjectTicket(
                $issue->getId(),
                $issue->getName(),
                $totalTicketEstimated,
                $totalTicketSpent
            );

            $projectTicket->timesheets->add($timesheets);

            if ($hourReportData->projectTags->containsKey($issue->getEpicName())) {
                $projectTag = $hourReportData->projectTags->get($issue->getEpicName());
                $projectTag->totalEstimated += $totalTicketEstimated;
                $projectTag->totalSpent += $totalTicketSpent;
            } else {
                $projectTag = new HourReportProjectTag($totalTicketEstimated, $totalTicketSpent, $issue->getEpicName());
            }
            $projectTag->projectTickets->add($projectTicket);
            $hourReportData->projectTags->set($issue->getEpicName(), $projectTag);
            $hourReportData->projectTotalEstimated += $totalTicketEstimated;
            $hourReportData->projectTotalSpent += $totalTicketSpent;
        }

        return $hourReportData;
    }

    private function processTimesheetsData($timesheetsData): array
    {
        $timesheets = [];
        $totalTicketSpent = 0;

        foreach ($timesheetsData as $timesheetDatum) {
            $hoursSpent = (float) ($timesheetDatum->getTimeSpentSeconds() / 3600);
            $timesheet = new HourReportTimesheet($timesheetDatum->getId(), $hoursSpent);
            $timesheets[] = $timesheet;
            $totalTicketSpent += $hoursSpent;
        }

        return [$timesheets, $totalTicketSpent];
    }
}
