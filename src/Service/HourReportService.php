<?php

namespace App\Service;

use App\Exception\EconomicsException;
use App\Model\Reports\HourReportData;
use App\Model\Reports\HourReportProjectTag;
use App\Model\Reports\HourReportProjectTicket;
use App\Model\Reports\HourReportTimesheet;
use App\Repository\IssueRepository;
use App\Repository\ProjectVersionBudgetRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;

class HourReportService
{
    public function __construct(
        private readonly ProjectVersionBudgetRepository $budgetRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly IssueRepository $issueRepository,
        private readonly WorklogRepository $worklogRepository,
    ) {
    }

    /**
     * @throws EconomicsException
     */
    public function getHourReport($projectId, $versionId): HourReportData
    {
        if (!$projectId) {
            throw new EconomicsException('No project id specified');
        }
        $hourReportData = new HourReportData(0, 0);
        $projectIssues = $this->issueRepository->findBy(['project_tracker_id' => $projectId]);

        foreach ($projectIssues as $issue) {
            $totalTicketEstimated = (float) $issue->planHours;
            $timesheetData = $this->worklogRepository->findBy(['issue_id' => $issue->getId()]);

            list($timesheets, $totalTicketSpent) = $this->processTimesheetsData($timesheetData);

            $projectTicket = new HourReportProjectTicket(
                $issue->getId(),
                $issue->getName(),
                $totalTicketEstimated,
                $totalTicketSpent
            );

            $projectTicket->timesheets->add($timesheets);

            if ($hourReportData->projectTags->containsKey($issue->tags)) {
                $projectTag = $hourReportData->projectTags->get($issue->tags);
                $projectTag->totalEstimated += $totalTicketEstimated;
                $projectTag->totalSpent += $totalTicketSpent;
            } else {
                $projectTag = new HourReportProjectTag($totalTicketEstimated, $totalTicketSpent, $issue->tags);
            }
            $projectTag->projectTickets->add($projectTicket);
            $hourReportData->projectTags->set($issue->tags, $projectTag);
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
            $timesheet = new HourReportTimesheet($timesheetDatum->id, $timesheetDatum->hours);
            $timesheets[] = $timesheet;
            $totalTicketSpent += (float) $timesheetDatum->hours;
        }

        return [$timesheets, $totalTicketSpent];
    }
}
