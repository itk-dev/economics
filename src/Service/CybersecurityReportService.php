<?php

namespace App\Service;

use App\Entity\Worklog;
use App\Model\Reports\CybersecurityProjectData;
use App\Model\Reports\CybersecurityReportData;
use App\Model\Reports\CybersecurityTicketData;
use App\Model\Reports\CybersecurityWorklogData;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorklogRepository;

readonly class CybersecurityReportService
{
    private const SECONDS_TO_HOURS = 1 / 3600;

    public function __construct(
        private IssueRepository $issueRepository,
        private WorklogRepository $worklogRepository,
        private ProjectRepository $projectRepository,
    ) {
    }

    public function getCybersecurityReport(
        ?\DateTimeInterface $fromDate,
        ?\DateTimeInterface $toDate,
        string $versionTitle,
    ): CybersecurityReportData {
        $report = new CybersecurityReportData();

        // Fetch all project IDs that have a cybersecurity agreement
        $projectIdsWithAgreement = array_flip(
            $this->projectRepository->getProjectIdsWithCybersecurityAgreement()
        );

        // Fetch issues that have the version with the given title
        $issues = $this->issueRepository->issuesContainingVersionTitle($versionTitle);

        foreach ($issues as $issue) {
            // Fetch worklogs for this issue restricted to the period
            $worklogs = $this->worklogRepository->getWorklogsByIssueAndPeriod(
                $issue->getId(),
                $fromDate,
                $toDate
            );

            // Sum total time spent (seconds → hours)
            $totalTicketSpent = array_reduce(
                $worklogs,
                fn (float $carry, Worklog $w) => $carry + ($w->getTimeSpentSeconds() * self::SECONDS_TO_HOURS),
                0
            );

            if (0 === $totalTicketSpent) {
                continue;
            }
            $projectEntity = $issue->getProject();
            $projectId = $projectEntity->getId();
            $projectName = $projectEntity->getName();

            // Create project entry once
            if (!isset($report->projects[$projectName])) {
                $projectData = new CybersecurityProjectData($projectName);
                $projectData->hasCybersecurityAgreement =
                    isset($projectIdsWithAgreement[$projectId]);

                $report->projects[$projectName] = $projectData;
            }

            // Create worklog DTOs
            $worklogData = array_map(
                fn (Worklog $w) => new CybersecurityWorklogData(
                    $w->getId(),
                    $w->getTimeSpentSeconds() * self::SECONDS_TO_HOURS,
                    $w->getDescription(),
                    $w->getWorker()
                ),
                $worklogs
            );

            // Create ticket DTO
            $ticket = new CybersecurityTicketData(
                $issue->getId(),
                $issue->getProjectTrackerId(),
                $issue->getName(),
                $totalTicketSpent,
                $issue->getLinkToIssue(),
                $worklogData
            );

            // Attach ticket to project
            $project = $report->projects[$projectName];
            $project->tickets[] = $ticket;
            $project->totalSpent += $totalTicketSpent;

            $report->totalSpent += $totalTicketSpent;
        }

        return $report;
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
