<?php

namespace App\Service;

use App\Exception\EconomicsException;
use App\Model\Reports\HourReportData;
use App\Model\Reports\HourReportProjectTag;
use App\Model\Reports\HourReportProjectTicket;
use App\Model\Reports\HourReportTimesheet;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;

class HourReportService
{
    public function __construct(
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

    public function getMilestones(string $projectId, bool $includeAllOption = false): array
    {
        $milestones = $this->versionRepository->findBy(['project' => $projectId]);
        $milestoneChoices = [];
        if ($includeAllOption) {
            $milestoneChoices['All milestones'] = '0';
        }
        foreach ($milestones as $milestone) {
            $milestoneName = $milestone->getName() ?? '';
            $milestoneChoices[$milestoneName] = $milestone->getId();
        }

        return $milestoneChoices;
    }

    /**
     * @throws EconomicsException
     */
    public function getHourReport(string $projectId, ?\DateTimeInterface $fromDate, ?\DateTimeInterface $toDate, int $versionId = null): HourReportData
    {
        if (!$projectId) {
            throw new EconomicsException('No project id specified');
        }
        $hourReportData = new HourReportData(0, 0);

        // If version is provided, we only want the issues containing the versionId
        if ($versionId) {
            $projectIssues = $this->issueRepository->issueContainingVersion($versionId);
        } else {
            $projectIssues = $this->issueRepository->findBy(['project' => $projectId]);
        }

        foreach ($projectIssues as $issue) {
            $totalTicketEstimated = (float) $issue->planHours;

            $timesheetData = $this->worklogRepository->findBy(['issue' => $issue->getId()]);

            list($timesheets, $totalTicketSpent) = $this->processTimesheetsData($timesheetData, $fromDate, $toDate);

            $projectTicket = new HourReportProjectTicket(
                $issue->getId(),
                $issue->getName(),
                $totalTicketEstimated,
                $totalTicketSpent
            );

            $projectTicket->timesheets->add($timesheets);

            $issueEpicName = $issue->getEpicName() ?? '';

            if ($hourReportData->projectTags->containsKey($issueEpicName)) {
                $projectTag = $hourReportData->projectTags->get((string) $issueEpicName);
                if ($projectTag) {
                    $projectTag->totalEstimated += $totalTicketEstimated;
                    $projectTag->totalSpent += $totalTicketSpent;
                }
            } else {
                $projectTag = new HourReportProjectTag($totalTicketEstimated, $totalTicketSpent, (string) $issueEpicName);
            }

            if (!$projectTag) {
                throw new EconomicsException('Project tag not found');
            }
            $projectTag->projectTickets->add($projectTicket);

            $hourReportData->projectTags->set((string) $issueEpicName, $projectTag);
            $hourReportData->projectTotalEstimated += $totalTicketEstimated;
            $hourReportData->projectTotalSpent += $totalTicketSpent;
        }

        return $hourReportData;
    }

    private function processTimesheetsData($timesheetsData, $fromDate, $toDate): array
    {
        $timesheets = [];
        $totalTicketSpent = 0;

        foreach ($timesheetsData as $timesheetDatum) {
            if ($fromDate && $toDate) {
                $timesheetDate = $timesheetDatum->getStarted();
                if ($timesheetDate < $fromDate || $timesheetDate > $toDate) {
                    continue;
                }
            }

            $hoursSpent = (float) ($timesheetDatum->getTimeSpentSeconds() / 3600);
            $timesheet = new HourReportTimesheet($timesheetDatum->getId(), $hoursSpent);
            $timesheets[] = $timesheet;
            $totalTicketSpent += $hoursSpent;
        }

        return [$timesheets, $totalTicketSpent];
    }

    public function getFromDate(): string
    {
        $fromDate = new \DateTime();
        $fromDate->modify('first day of this month');

        return $fromDate->format('Y-m-d');
    }

    public function getToDate(): string
    {
        $fromDate = new \DateTime();

        return $fromDate->format('Y-m-d');
    }
}
