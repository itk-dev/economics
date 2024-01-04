<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\DataProvider;
use App\Entity\Invoice;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Repository\AccountRepository;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataSynchronizationService
{
    private const BATCH_SIZE = 200;
    private const MAX_RESULTS = 50;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ClientRepository $clientRepository,
        private readonly VersionRepository $versionRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly IssueRepository $issueRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly AccountRepository $accountRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly DataProviderService $dataProviderService,
    ) {
    }

    /**
     * Synchronize projects from DataProviders.
     *
     * @throws UnsupportedDataProviderException
     */
    public function syncProjects(callable $progressCallback, DataProvider $dataProvider): void
    {
        $service = $this->dataProviderService->getService($dataProvider);

        // Get all projects from ApiService.
        $allProjectData = $service->getAllProjectData();

        foreach ($allProjectData as $index => $projectDatum) {
            $project = $this->projectRepository->findOneBy(['projectTrackerId' => $projectDatum->projectTrackerId]);

            if (!$project) {
                $project = new Project();
                $this->entityManager->persist($project);
            }

            $project->setName($projectDatum->name);
            $project->setProjectTrackerId($projectDatum->projectTrackerId);
            $project->setProjectTrackerKey($projectDatum->projectTrackerKey);
            $project->setProjectTrackerProjectUrl($projectDatum->projectTrackerProjectUrl);

            foreach ($projectDatum->versions as $versionData) {
                $version = $this->versionRepository->findOneBy(['projectTrackerId' => $versionData->projectTrackerId]);

                if (!$version) {
                    $version = new Version();
                    $this->entityManager->persist($version);
                }

                $version->setName($versionData->name);
                $version->setProjectTrackerId($versionData->projectTrackerId);
                $version->setProject($project);
            }

            // Only synchronize clients if this is enabled.
            if ($dataProvider->isEnableClientSync()) {
                $projectClientData = $service->getClientDataForProject($projectDatum->projectTrackerId);

                foreach ($projectClientData as $clientData) {
                    $client = $this->clientRepository->findOneBy(['projectTrackerId' => $clientData->projectTrackerId]);

                    if (!$client) {
                        $client = new Client();
                        $client->setProjectTrackerId($clientData->projectTrackerId);
                        $this->entityManager->persist($client);
                    }

                    $client->setName($clientData->name);
                    $client->setContact($clientData->contact);
                    $client->setAccount($clientData->account);
                    $client->setType($clientData->type);
                    $client->setPsp($clientData->psp);
                    $client->setEan($clientData->ean);
                    $client->setStandardPrice($clientData->standardPrice);
                    $client->setCustomerKey($clientData->customerKey);
                    $client->setSalesChannel($clientData->salesChannel);
                    $client->setProjectLeadName($clientData->projectLeadName);
                    $client->setProjectLeadMail($clientData->projectLeadMail);

                    if (!$client->getProjects()->contains($client)) {
                        $client->addProject($project);
                    }
                }
            }

            // Flush and clear for each batch.
            if (0 === intval($index) % self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            $progressCallback($index, count($allProjectData));
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Synchronize accounts from DataProviders.
     * @throws UnsupportedDataProviderException
     */
    public function syncAccounts(callable $progressCallback, DataProvider $dataProvider): void
    {
        if ($dataProvider->isEnableAccountSync()) {
            $service = $this->dataProviderService->getService($dataProvider);

            $projectTrackerIdentifier = $service->getProjectTrackerIdentifier();

            // Get all accounts from ApiService.
            $allAccountData = $service->getAllAccountData();

            foreach ($allAccountData as $index => $accountDatum) {
                $account = $this->accountRepository->findOneBy(['projectTrackerId' => $accountDatum->projectTrackerId, 'source' => $projectTrackerIdentifier]);

                if (!$account) {
                    $account = new Account();
                    $account->setSource($projectTrackerIdentifier);
                    $account->setProjectTrackerId($accountDatum->projectTrackerId);

                    $this->entityManager->persist($account);
                }

                $account->setName($accountDatum->name);
                $account->setValue($accountDatum->value);
                $account->setStatus($accountDatum->status);
                $account->setCategory($accountDatum->category);

                // Flush and clear for each batch.
                if (0 === intval($index) % self::BATCH_SIZE) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }

                $progressCallback($index, count($allAccountData));
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    /**
     * Synchronize issues from DataProvider.
     *
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    public function syncIssuesForProject(int $projectId, callable $progressCallback = null, DataProvider $dataProvider): void
    {
        $service = $this->dataProviderService->getService($dataProvider);

        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            throw new EconomicsException($this->translator->trans('exception.project_not_found'));
        }

        $projectTrackerId = $project->getProjectTrackerId();

        if (null === $projectTrackerId) {
            throw new EconomicsException($this->translator->trans('exception.project_tracker_id_not_set'));
        }

        $issuesProcessed = 0;

        $startAt = 0;

        do {
            $project = $this->projectRepository->find($projectId);

            if (!$project) {
                throw new EconomicsException($this->translator->trans('exception.project_not_found'));
            }

            $pagedIssueData = $service->getIssuesDataForProjectPaged($projectTrackerId, $startAt, self::MAX_RESULTS);
            $total = $pagedIssueData->total;

            $issueData = $pagedIssueData->items;

            foreach ($issueData as $issueDatum) {
                $issue = $this->issueRepository->findOneBy(['projectTrackerId' => $issueDatum->projectTrackerId]);

                if (!$issue) {
                    $issue = new Issue();

                    $this->entityManager->persist($issue);
                }

                $issue->setName($issueDatum->name);
                $issue->setAccountId($issueDatum->accountId);
                $issue->setAccountKey($issueDatum->accountKey);
                $issue->setEpicKey($issueDatum->epicKey);
                $issue->setEpicName($issueDatum->epicName);
                $issue->setProject($project);
                $issue->setProjectTrackerId($issueDatum->projectTrackerId);
                $issue->setProjectTrackerKey($issueDatum->projectTrackerKey);
                $issue->setResolutionDate($issueDatum->resolutionDate);
                $issue->setStatus($issueDatum->status);

                if (null == $issue->getSource()) {
                    $issue->setSource($service->getProjectTrackerIdentifier());
                }

                foreach ($issueDatum->versions as $versionData) {
                    $version = $this->versionRepository->findOneBy(['projectTrackerId' => $versionData->projectTrackerId]);

                    if (null !== $version) {
                        $issue->addVersion($version);
                    }
                }

                if (null !== $progressCallback) {
                    $progressCallback($issuesProcessed, $total);
                    ++$issuesProcessed;
                }
            }

            $startAt += self::MAX_RESULTS;

            $this->entityManager->flush();
            $this->entityManager->clear();
        } while ($startAt < $total);

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Synchronize worklogs from DataProvider.
     *
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    public function syncWorklogsForProject(int $projectId, callable $progressCallback = null, DataProvider $dataProvider): void
    {
        $service = $this->dataProviderService->getService($dataProvider);

        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            throw new EconomicsException($this->translator->trans('exception.project_not_found'));
        }

        $projectTrackerId = $project->getProjectTrackerId();

        if (null === $projectTrackerId) {
            throw new EconomicsException($this->translator->trans('exception.project_tracker_id_not_set'));
        }

        $worklogData = $service->getWorklogDataForProject($projectTrackerId);
        $worklogsAdded = 0;

        foreach ($worklogData as $worklogDatum) {
            $project = $this->projectRepository->find($projectId);

            if (!$project) {
                throw new EconomicsException($this->translator->trans('exception.project_not_found'));
            }

            $worklog = $this->worklogRepository->findOneBy(['worklogId' => $worklogDatum->projectTrackerId]);

            if (!$worklog) {
                $worklog = new Worklog();

                $this->entityManager->persist($worklog);
            }

            $worklog->setWorklogId($worklogDatum->projectTrackerId);
            $worklog->setDescription($worklogDatum->comment);
            $worklog->setWorker($worklogDatum->worker);
            $worklog->setStarted($worklogDatum->started);
            $worklog->setProjectTrackerIssueId($worklogDatum->projectTrackerIssueId);
            $worklog->setTimeSpentSeconds($worklogDatum->timeSpentSeconds);

            if (null !== $worklog->getProjectTrackerIssueId()) {
                $issue = $this->issueRepository->findOneBy(['projectTrackerId' => $worklog->getProjectTrackerIssueId()]);
                $worklog->setIssue($issue);
            }

            if (!$worklog->isBilled() && $worklogDatum->projectTrackerIsBilled) {
                $worklog->setIsBilled(true);
                $worklog->setBilledSeconds($worklogDatum->timeSpentSeconds);
            }

            if (null == $worklog->getSource()) {
                $worklog->setSource($service->getProjectTrackerIdentifier());
            }

            $project->addWorklog($worklog);

            if (null !== $progressCallback) {
                $progressCallback($worklogsAdded, count($worklogData));

                ++$worklogsAdded;
            }

            // Flush and clear for each batch.
            if (0 === $worklogsAdded % self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Migrate from invoice.customer_account_id to invoice.client.
     *
     * Should only be run when migrating from JiraEconomics to Economics.
     */
    public function migrateCustomers(): void
    {
        $invoices = $this->invoiceRepository->findAll();

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            $customerAccountId = $invoice->getCustomerAccountId();

            if (null == $invoice->getClient() && null !== $customerAccountId) {
                $client = $this->clientRepository->findOneBy(['projectTrackerId' => $invoice->getCustomerAccountId()]);

                if (null !== $client) {
                    $invoice->setClient($client);
                }
            }
        }

        $this->entityManager->flush();
    }
}
