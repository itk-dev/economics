<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Exception\EconomicsException;
use App\Model\Reports\HourReportData;
use App\Model\Reports\HourReportProjectTag;
use App\Model\Reports\HourReportProjectTicket;
use App\Model\Reports\HourReportTimesheet;
use App\Repository\IssueRepository;
use App\Repository\WorklogRepository;

class HourReportService
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly WorklogRepository $worklogRepository,
    ) {
    }

    /**
     * @throws EconomicsException
     */
    public function getHourReport(Project $project, ?\DateTimeInterface $fromDate, ?\DateTimeInterface $toDate, Version $version = null): HourReportData
    {
        $hourReportData = new HourReportData(0, 0);

        // If version is provided, we only want the issues containing the version.
        if ($version) {
            $projectIssues = $this->issueRepository->issuesContainingVersion($version);
        } else {
            $projectIssues = $this->issueRepository->findBy(['project' => $project]);
        }

        foreach ($projectIssues as $issue) {
            $totalTicketEstimated = (float) $issue->planHours;
            $dueDate = $issue->getDueDate();

            $timesheetData = $this->worklogRepository->findBy(['issue' => $issue->getId()]);

            list($timesheets, $totalTicketSpent) = $this->processTimesheetsData($timesheetData, $fromDate, $toDate);

            // If no worklogs have been registered in the interval or if the due date is not in the interval,
            // ignore the issue in the report.
            if ($totalTicketSpent === 0 || $dueDate < $fromDate || $dueDate > $toDate) {
                continue;
            }

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

    private function processTimesheetsData(array $timesheetsData, \DateTimeInterface $fromDate = null, \DateTimeInterface $toDate = null): array
    {
        $timesheets = [];
        $totalTicketSpent = 0;

        /** @var Worklog $timesheetDatum */
        foreach ($timesheetsData as $timesheetDatum) {
            $timesheetDate = $timesheetDatum->getStarted();

            if ($fromDate !== null) {
                if ($timesheetDate < $fromDate) {
                    continue;
                }
            }

            if ($toDate !== null) {
                if ($timesheetDate > $toDate) {
                    continue;
                }
            }

            $hoursSpent = $timesheetDatum->getTimeSpentSeconds() / 3600;
            $timesheet = new HourReportTimesheet($timesheetDatum->getId(), $hoursSpent);
            $timesheets[] = $timesheet;
            $totalTicketSpent += $hoursSpent;
        }

        return [$timesheets, $totalTicketSpent];
    }

    public function getDefaultFromDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('first day of this month');

        return $fromDate;
    }

    public function getDefaultToDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('last day of this month');

        return $fromDate;
    }
}
