<?php

namespace App\Service;

use App\Exception\EconomicsException;
use App\Model\Reports\ForecastReportData;
use App\Model\Reports\ForecastReportIssueData;
use App\Model\Reports\ForecastReportIssueVersionData;
use App\Model\Reports\ForecastReportProjectData;
use App\Model\Reports\ForecastReportWorklogData;
use App\Repository\WorkerRepository;
use App\Repository\WorklogRepository;

class ForecastReportService
{
    public function __construct(
        private readonly WorklogRepository $worklogRepository,
        private readonly WorkerRepository $workerRepository,
    ) {
    }

    /**
     * @throws EconomicsException
     * @throws \Exception
     */
    public function getForecastReport(?\DateTimeInterface $fromDate, ?\DateTimeInterface $toDate): ForecastReportData
    {
        // Get all worklogs attached to an invoice for the period
        $invoiceAttachedWorklogs = $this->worklogRepository->getWorklogsAttachedToInvoiceInDateRange($fromDate, $toDate);
        $forecastReportData = new ForecastReportData();

        foreach ($invoiceAttachedWorklogs as $worklog) {
            $projectId = $worklog->getProject()->getId();

            // Add project entry
            if (!isset($forecastReportData->projects[$projectId])) {
                $forecastReportData->projects[$projectId] = new ForecastReportProjectData($projectId);
                $forecastReportData->projects[$projectId]->projectName = $worklog->getProject()->getName();
            }

            $currentProject = $forecastReportData->projects[$projectId];
            $worklogTime = ($worklog->getTimeSpentSeconds() / 3600);
            $isWorklogBilled = $worklog->isBilled();

            // Count up total project hours
            $currentProject->invoiced += $worklogTime;
            if ($isWorklogBilled) {
                $currentProject->invoicedAndRecorded += $worklogTime;
            }

            $issueId = $worklog->getIssue()->getProjectTrackerKey();
            $issueLink = $worklog->getIssue()->getLinkToIssue();
            $issueTag = $worklog->getIssue()->getEpicName() ?: '[no tag]';

            // Add issue entry
            if (!isset($currentProject->issues[$issueTag])) {
                $currentProject->issues[$issueTag] = new ForecastReportIssueData($issueTag);
                $currentProject->issues[$issueTag]->issueId = $issueId;
                $currentProject->issues[$issueTag]->issueLink = $issueLink;
            }

            $currentIssue = $currentProject->issues[$issueTag];

            $currentIssue->invoiced += $worklogTime;
            if ($isWorklogBilled) {
                $currentIssue->invoicedAndRecorded += $worklogTime;
            }

            $issueVersions = $worklog->getIssue()->getVersions();
            $issueVersion = count($issueVersions) > 0 ? implode('', array_map(function($version) { return $version->getName(); }, $issueVersions->toArray())) : '[no version]';

            $issueVersionIdentifier = $issueTag.$issueVersion;

            if (!isset($currentIssue->versions[$issueVersion])) {
                $currentIssue->versions[$issueVersion] = new ForecastReportIssueVersionData($issueVersion);
                $currentIssue->versions[$issueVersion]->issueVersionIdentifier = $issueVersionIdentifier;
            }

            $currentVersion = $currentIssue->versions[$issueVersion];

            $currentVersion->invoiced += $worklogTime;

            if ($isWorklogBilled) {
                $currentVersion->invoicedAndRecorded += $worklogTime;
            }

            $worklogId = $worklog->getId();
            $workerEmail = $worklog->getWorker();
            $worker = $this->workerRepository->findOneBy(['email' => $workerEmail]);
            $workerName = $worker->getName() ?? '[no worker]';
            $description = $worklog->getDescription();

            if (!isset($currentVersion->worklogs[$worklogId])) {
                $currentVersion->worklogs[$worklogId] = new ForecastReportWorklogData($worklogId, $description);
                $currentVersion->worklogs[$worklogId]->worker = $workerName;
                $currentVersion->worklogs[$worklogId]->description = $description;
            }

            $currentWorklog = $currentVersion->worklogs[$worklogId];

            $currentWorklog->invoiced += $worklogTime;

            if ($isWorklogBilled) {
                $currentWorklog->invoicedAndRecorded += $worklogTime;
            }

            // Add up grand totals
            $forecastReportData->totalInvoiced += $worklogTime;
            if ($isWorklogBilled) {
                $forecastReportData->totalInvoicedAndRecorded += $worklogTime;
            }
        }

        return $forecastReportData;
    }
}
