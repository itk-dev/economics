<?php

namespace App\Service;

use App\Entity\Version;
use App\Entity\Worklog;
use App\Model\Reports\CybersecurityProjectData;
use App\Model\Reports\CybersecurityReportData;
use App\Model\Reports\CybersecurityTicketData;
use App\Model\Reports\CybersecurityWorklogData;
use App\Model\Reports\HourReportWorklog;
use App\Repository\IssueRepository;
use App\Repository\WorklogRepository;

class CybersecurityReportService
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly WorklogRepository $worklogRepository,
    ) {
    }

    public function getCybersecurityReport(
        ?\DateTimeInterface $fromDate,
        ?\DateTimeInterface $toDate,
        ?Version $version,
    ): CybersecurityReportData {
        $report = new CybersecurityReportData();

        if (!$version) {
            return $report;
        }

        $issues = $this->issueRepository
            ->issuesContainingVersion($version);

        foreach ($issues as $issue) {
            $timesheetData = $this->worklogRepository->findBy([
                'issue' => $issue->getId(),
            ]);

            [$timesheets, $totalTicketSpent] = $this->processTimesheetsData(
                $timesheetData,
                $fromDate,
                $toDate
            );

            // Skip tickets without logged hours
            if (0.0 === $totalTicketSpent) {
                continue;
            }

            $projectName = $issue->getProject()->getName();

            if (!isset($report->projects[$projectName])) {
                $report->projects[$projectName] = new CybersecurityProjectData(
                    $projectName
                );
            }

            $ticket = new CybersecurityTicketData(
                $issue->getId(),
                $issue->getProjectTrackerId(),
                $issue->getName(),
                $totalTicketSpent,
                $issue->getLinkToIssue(),
                array_map(
                    fn ($worklog) => new CybersecurityWorklogData(
                        $worklog->id,
                        $worklog->hours
                    ),
                    $timesheets
                )
            );

            $project = $report->projects[$projectName];
            $project->tickets[] = $ticket;
            $project->totalSpent += $totalTicketSpent;

            $report->totalSpent += $totalTicketSpent;
        }

        return $report;
    }

    private function processTimesheetsData(array $worklogs, ?\DateTimeInterface $fromDate = null, ?\DateTimeInterface $toDate = null): array
    {
        $timesheets = [];
        $totalTicketSpent = 0;

        /** @var Worklog $worklog */
        foreach ($worklogs as $worklog) {
            $timesheetDate = $worklog->getStarted();

            if (null !== $fromDate) {
                if ($timesheetDate < $fromDate) {
                    continue;
                }
            }

            if (null !== $toDate) {
                if ($timesheetDate > $toDate) {
                    continue;
                }
            }

            $hoursSpent = $worklog->getTimeSpentSeconds() / 3600;
            $timesheet = new HourReportWorklog($worklog->getId(), $hoursSpent);
            $timesheets[] = $timesheet;
            $totalTicketSpent += $hoursSpent;
        }

        return [$timesheets, $totalTicketSpent];
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getDefaultFromDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('first day of this month');

        return $fromDate;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getDefaultToDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('last day of this month');

        return $fromDate;
    }
}
