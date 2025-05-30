<?php

namespace App\Service;

use App\Model\Reports\ForecastReportData;
use App\Model\Reports\ForecastReportIssueData;
use App\Model\Reports\ForecastReportIssueVersionData;
use App\Model\Reports\ForecastReportProjectData;
use App\Model\Reports\ForecastReportWorklogData;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;

class ForecastReportService
{
    private const PAGE_SIZE = 200;

    public function __construct(
        private readonly WorklogRepository $worklogRepository,
        private readonly WorkerRepository $workerRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get forecast report data based on given date range.
     *
     * @param \DateTimeInterface $fromDate The start date of the period
     * @param \DateTimeInterface $toDate The end date of the period
     *
     * @return ForecastReportData The forecast report data
     *
     * @throws \Exception
     */
    public function getForecastReport(\DateTimeInterface $fromDate, \DateTimeInterface $toDate): ForecastReportData
    {
        $page = 1;
        $forecastReportData = new ForecastReportData();

        $workerNameMapping = array_reduce($this->workerRepository->findAll(), function ($carry, $worker) {
            $carry[$worker->getEmail()] = $worker->getName() ?? '[no worker]';

            return $carry;
        }, []);

        do {
            $invoiceAttachedWorklogs = $this->worklogRepository->getWorklogsAttachedToInvoiceInDateRange($fromDate, $toDate, $page, self::PAGE_SIZE);

            foreach ($invoiceAttachedWorklogs['paginator'] as $worklog) {
                // Loop through each worklog
                $projectId = $worklog->getProject()->getId();

                if (!$projectId) {
                    throw new \Exception('Project id is null');
                }
                // If the project isn't already in the forecast, add it
                if (!isset($forecastReportData->projects[$projectId])) {
                    $newForecastReportProjectData = new ForecastReportProjectData($projectId);
                    $newForecastReportProjectData->projectName = $worklog->getProject()?->getName() ?? '[no project name]';
                    $forecastReportData->projects[$projectId] = $newForecastReportProjectData;
                }
                // Get current project from forecast
                $currentProject = $forecastReportData->projects[$projectId];

                if (!$currentProject) {
                    throw new \Exception('Project instance was not found');
                }

                // Calculate worklog time in hours
                $worklogTime = ($worklog->getTimeSpentSeconds() / 3600);

                // Check if worklog is billed
                $isWorklogBilled = $worklog->isBilled();

                // Tally up total project hours based on whether the worklog is billed
                $currentProject->invoiced += $worklogTime;
                if ($isWorklogBilled) {
                    $currentProject->invoicedAndRecorded += $worklogTime;
                }

                // Get issue details from worklog
                $issueId = $worklog->getIssue()->getProjectTrackerKey();
                $issueLink = $worklog->getIssue()->getLinkToIssue();
                $issueTag = $worklog->getIssue()->getEpicName() ?: '[no tag]';

                // Add issue in the project if it does not exist
                if (!isset($currentProject->issues[$issueTag])) {
                    $currentProject->issues[$issueTag] = new ForecastReportIssueData($issueTag);
                    $currentProject->issues[$issueTag]->issueId = $issueId;
                    $currentProject->issues[$issueTag]->issueLink = $issueLink;
                }

                // Get current issue from project
                $currentIssue = $currentProject->issues[$issueTag];

                // Add up the invoiced hours to the current issue
                $currentIssue->invoiced += $worklogTime;
                if ($isWorklogBilled) {
                    $currentIssue->invoicedAndRecorded += $worklogTime;
                }

                // Get version details from issue
                $issueVersions = $worklog->getIssue()->getVersions();
                $issueVersion = count($issueVersions) > 0 ? implode(', ', array_map(function ($version) { return $version->getName(); }, $issueVersions->toArray())) : '[no version]';

                $issueVersionIdentifier = $issueTag.$issueVersion;

                // Add version entry in the issue if it does not exist
                if (!isset($currentIssue->versions[$issueVersion])) {
                    $currentIssue->versions[$issueVersion] = new ForecastReportIssueVersionData($issueVersion);
                    $currentIssue->versions[$issueVersion]->issueVersionIdentifier = $issueVersionIdentifier;
                }

                // Get the current version from issue
                $currentVersion = $currentIssue->versions[$issueVersion];

                // Add up invoiced hours in current version
                $currentVersion->invoiced += $worklogTime;

                // If worklog is billed, add it to the recorded hours as well
                if ($isWorklogBilled) {
                    $currentVersion->invoicedAndRecorded += $worklogTime;
                }

                // Get worklog details
                $worklogId = $worklog->getId();
                $workerEmail = $worklog->getWorker();
                $workerName = $workerNameMapping[$workerEmail] ?? '[no worker]';
                $description = $worklog->getDescription();

                // Add worklog entry in the version if it does not exist
                if (!isset($currentVersion->worklogs[$worklogId])) {
                    $currentVersion->worklogs[$worklogId] = new ForecastReportWorklogData($worklogId, $description);
                    $currentVersion->worklogs[$worklogId]->worker = $workerName;
                    $currentVersion->worklogs[$worklogId]->description = $description;
                }

                // Get the current worklog from the version
                $currentWorklog = $currentVersion->worklogs[$worklogId];

                // Add up invoiced hours in the current worklog
                $currentWorklog->invoiced += $worklogTime;

                // If worklog is billed, add it to the recorded hours as well
                if ($isWorklogBilled) {
                    $currentWorklog->invoicedAndRecorded += $worklogTime;
                }

                // Add up grand totals for the entire forecast
                $forecastReportData->totalInvoiced += $worklogTime;
                if ($isWorklogBilled) {
                    $forecastReportData->totalInvoicedAndRecorded += $worklogTime;
                }
            }
            $this->entityManager->clear();
            ++$page;
        } while ($page <= $invoiceAttachedWorklogs['pages_count']);

        // Return populated forecast report data
        return $forecastReportData;
    }

    /**
     * Gets the first day of the last month.
     *
     * @return \DateTime The default from date
     */
    public function getDefaultFromDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('first day of last month');

        return $fromDate;
    }

    /**
     * Gets the last day of the last month.
     *
     * @return \DateTime The default "to" date
     */
    public function getDefaultToDate(): \DateTime
    {
        $fromDate = new \DateTime();
        $fromDate->modify('last day of last month');

        return $fromDate;
    }
}
