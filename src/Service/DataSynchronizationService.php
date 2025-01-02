<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\DataProvider;
use App\Entity\Epic;
use App\Entity\Invoice;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\Worklog;
use App\Enum\BillableKindsEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Model\SprintReport\SprintReportVersion;
use App\Repository\AccountRepository;
use App\Repository\ClientRepository;
use App\Repository\DataProviderRepository;
use App\Repository\EpicRepository;
use App\Repository\InvoiceRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorkerRepository;
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
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly WorkerRepository $workerRepository,
        private readonly EpicRepository $epicRepository,
    ) {
    }

    /**
     * Synchronize projects from DataProviders.
     *
     * @throws UnsupportedDataProviderException
     */
    public function syncProjects(callable $progressCallback, DataProvider $dataProvider): void
    {
        $dataProviderId = $dataProvider->getId();

        $service = $this->dataProviderService->getService($dataProvider);

        // Get all projects from ApiService.
        $allProjectData = $service->getProjectDataCollection();
        foreach ($allProjectData->projectData as $index => $projectDatum) {
            $project = $this->projectRepository->findOneBy([
                'projectTrackerId' => $projectDatum->projectTrackerId,
                'dataProvider' => $dataProvider,
            ]);
            $dataProvider = $this->dataProviderRepository->find($dataProviderId);

            if (!$project) {
                $project = new Project();
                $project->setDataProvider($dataProvider);
                $this->entityManager->persist($project);
            }

            $project->setName($projectDatum->name);
            $project->setProjectTrackerId($projectDatum->projectTrackerId);
            $project->setProjectTrackerKey($projectDatum->projectTrackerKey);
            $project->setProjectTrackerProjectUrl($projectDatum->projectTrackerProjectUrl);

            foreach ($projectDatum->versions as $versionData) {
                /** @var SprintReportVersion $versionDatum */
                foreach ($versionData as $versionDatum) {
                    $version = $this->versionRepository->findOneBy([
                        'projectTrackerId' => $versionDatum->id,
                        'dataProvider' => $dataProvider,
                    ]);

                    if (!$version) {
                        $version = new Version();
                        $version->setDataProvider($dataProvider);
                        $this->entityManager->persist($version);
                    }

                    $version->setName($versionDatum->name);
                    $version->setProjectTrackerId($versionDatum->id);
                    $version->setProject($project);
                }
            }

            // Only synchronize clients if this is enabled.
            if (null != $dataProvider && $dataProvider->isEnableClientSync()) {
                $projectClientData = $service->getClientDataForProject($projectDatum->projectTrackerId);

                foreach ($projectClientData as $clientData) {
                    $client = $this->clientRepository->findOneBy([
                        'projectTrackerId' => $clientData->projectTrackerId,
                        'dataProvider' => $dataProvider,
                    ]);

                    if (!$client) {
                        $client = new Client();
                        $client->setDataProvider($dataProvider);
                        $client->setProjectTrackerId($clientData->projectTrackerId);
                        $this->entityManager->persist($client);
                    }

                    $client->setName($clientData->name);
                    $client->setContact($clientData->contact);
                    $client->setType($clientData->type);
                    $client->setPsp($clientData->psp);
                    $client->setEan($clientData->ean);
                    $client->setStandardPrice($clientData->standardPrice);
                    $client->setCustomerKey($clientData->customerKey);

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

            $progressCallback($index, count($allProjectData->projectData));
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * Synchronize accounts from DataProviders.
     *
     * @throws UnsupportedDataProviderException
     */
    public function syncAccounts(callable $progressCallback, DataProvider $dataProvider): void
    {
        $dataProviderId = $dataProvider->getId();

        if ($dataProvider->isEnableAccountSync()) {
            $service = $this->dataProviderService->getService($dataProvider);

            // Get all accounts from ApiService.
            $allAccountData = $service->getAllAccountData();

            foreach ($allAccountData as $index => $accountDatum) {
                $account = $this->accountRepository->findOneBy([
                    'projectTrackerId' => $accountDatum->projectTrackerId,
                    'dataProvider' => $dataProvider,
                ]);

                if (!$account) {
                    $account = new Account();
                    $dataProvider = $this->dataProviderRepository->find($dataProviderId);
                    $account->setDataProvider($dataProvider);
                    $account->setProjectTrackerId($accountDatum->projectTrackerId);

                    $this->entityManager->persist($account);
                }

                $account->setName($accountDatum->name);
                $account->setValue($accountDatum->value);

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
    public function syncIssuesForProject(int $projectId, ?callable $progressCallback = null, DataProvider $dataProvider): void
    {
        $dataProviderId = $dataProvider->getId();

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
            $dataProvider = $this->dataProviderRepository->find($dataProviderId);
            $project = $this->projectRepository->find($projectId);
            if (!$project) {
                throw new EconomicsException($this->translator->trans('exception.project_not_found'));
            }

            $pagedIssueData = $service->getIssuesDataForProjectPaged($projectTrackerId, $startAt, self::MAX_RESULTS);
            $total = $pagedIssueData->total;

            foreach ($pagedIssueData->items as $issueDatum) {
                $issue = $this->issueRepository->findOneBy([
                    'projectTrackerId' => $issueDatum->projectTrackerId,
                    'dataProvider' => $dataProvider,
                ]);

                if (!$issue) {
                    $issue = new Issue();
                    $issue->setDataProvider($dataProvider);

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
                $issue->setPlanHours($issueDatum->planHours);
                $issue->setHoursRemaining($issueDatum->hourRemaining);
                $issue->setDueDate($issueDatum->dueDate);
                $issue->setWorker($issueDatum->worker);
                $issue->setLinkToIssue($issueDatum->linkToIssue);

                // Leantime (as of now) supports only a single version (milestone) per issue.
                if (LeantimeApiService::class === $dataProvider?->getClass()) {
                    $issue->getVersions()->clear();
                }

                foreach ($issueDatum->versions as $versionData) {
                    $version = $this->versionRepository->findOneBy([
                        'projectTrackerId' => $versionData->projectTrackerId,
                        'dataProvider' => $dataProvider,
                    ]);

                    if (null !== $version) {
                        $issue->addVersion($version);
                    }
                }

                $epicArray = explode(',', $issueDatum->epicName);

                foreach ($epicArray as $epicTitle) {
                    if (empty($epicTitle)) {
                        continue;
                    }
                    $epic = $this->epicRepository->findOneBy([
                        'title' => $epicTitle,
                    ]);

                    if (!$epic) {
                        $epic = new Epic();
                        $epic->setTitle($epicTitle);
                        $this->entityManager->persist($epic);
                        $this->entityManager->flush();
                    }

                    $issue->addEpic($epic);
                }

                if (null !== $progressCallback) {
                    $progressCallback($issuesProcessed, $total);
                    ++$issuesProcessed;
                }
            }

            $startAt += $pagedIssueData->maxResults;

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
    public function syncWorklogsForProject(int $projectId, ?callable $progressCallback = null, DataProvider $dataProvider): void
    {
        $dataProviderId = $dataProvider->getId();

        $service = $this->dataProviderService->getService($dataProvider);

        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            throw new EconomicsException($this->translator->trans('exception.project_not_found'));
        }

        $projectTrackerId = $project->getProjectTrackerId();

        if (null === $projectTrackerId) {
            throw new EconomicsException($this->translator->trans('exception.project_tracker_id_not_set'));
        }

        // Some worklogs may have been deleted in the source.
        // Mark all project worklogs that are NOT
        //
        // * billed
        // * invoiced
        //
        // as candidates for deletion.
        //
        // Due to flushing below, we store only this worklogs IDs
        // (if we load worklog entities, they will become detached when flushing).
        /** @var array<int> $worklogsToDeleteIds */
        $worklogsToDeleteIds = array_map(
            static fn (Worklog $worklog) => $worklog->getId(),
            array_filter(
                $this->worklogRepository->findBy(['project' => $project]),
                static fn (Worklog $worklog): bool => !$worklog->isBilled() || null === $worklog->getInvoiceEntry()
            )
        );
        // Index by ID
        $worklogsToDeleteIds = array_combine($worklogsToDeleteIds, $worklogsToDeleteIds);

        $worklogData = $service->getWorklogDataCollection($projectTrackerId);
        $worklogsAdded = 0;
        foreach ($worklogData->worklogData as $worklogDatum) {
            $project = $this->projectRepository->find($projectId);

            if (!$project) {
                throw new EconomicsException($this->translator->trans('exception.project_not_found'));
            }

            $issue = !empty($worklogDatum->projectTrackerIssueId) ? $this->issueRepository->findOneBy([
                'projectTrackerId' => $worklogDatum->projectTrackerIssueId,
                'dataProvider' => $dataProvider,
            ]) : null;

            if (null === $issue) {
                // A worklog should always have an issue, so ignore the worklog.
                continue;
            }

            $worklog = $this->worklogRepository->findOneBy([
                'worklogId' => $worklogDatum->projectTrackerId,
                'dataProvider' => $dataProvider,
            ]);

            if (!$worklog) {
                $worklog = new Worklog();

                $dataProvider = $this->dataProviderRepository->find($dataProviderId);

                $worklog->setDataProvider($dataProvider);

                $this->entityManager->persist($worklog);
            }

            $worklog
                ->setWorklogId($worklogDatum->projectTrackerId)
                ->setDescription($worklogDatum->comment)
                ->setWorker($worklogDatum->worker)
                ->setStarted($worklogDatum->started)
                ->setProjectTrackerIssueId($worklogDatum->projectTrackerIssueId)
                ->setTimeSpentSeconds($worklogDatum->timeSpentSeconds)
                ->setTimeSpentSeconds($worklogDatum->timeSpentSeconds)
                ->setIssue($issue)
                ->setKind(BillableKindsEnum::tryFrom($worklogDatum->kind));

            if (null != $worklog->getProjectTrackerIssueId()) {
                $issue = $this->issueRepository->findOneBy(['projectTrackerId' => $worklog->getProjectTrackerIssueId()]);
                $worklog->setIssue($issue);
            }

            if (!$worklog->isBilled() && $worklogDatum->projectTrackerIsBilled) {
                $worklog->setIsBilled(true);
                $worklog->setBilledSeconds($worklogDatum->timeSpentSeconds);
            }

            $project->addWorklog($worklog);
            // Keep the worklog.
            $worklogId = $worklog->getId();

            if (null !== $worklogId) {
                unset($worklogsToDeleteIds[$worklogId]);
            }

            if (null !== $progressCallback) {
                $progressCallback($worklogsAdded, count($worklogData->worklogData));

                ++$worklogsAdded;
            }

            $workerEmail = $worklog->getWorker();

            if ($workerEmail && filter_var($workerEmail, FILTER_VALIDATE_EMAIL)) {
                $worker = $this->workerRepository->findOneBy(['email' => $workerEmail]);

                    if (!$worker) {
                        $worker = new Worker();
                        $worker->setEmail($workerEmail);

                        $this->entityManager->persist($worker);
                        $this->entityManager->flush();
                    }
                }

                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

            $startAt += $pagedWorklogData->maxResults;
            $this->entityManager->flush();
            $this->entityManager->clear();
        } while ($startAt < $total);

        $worklogsToDelete = $this->worklogRepository->findBy(['id' => $worklogsToDeleteIds]);
        foreach ($worklogsToDelete as $worklog) {
            $project->removeWorklog($worklog);
            $this->entityManager->remove($worklog);
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
                $client = $this->clientRepository->findOneBy([
                    'projectTrackerId' => $invoice->getCustomerAccountId(),
                ]);

                if (null !== $client) {
                    $invoice->setClient($client);
                }
            }
        }

        $this->entityManager->flush();
    }
}
