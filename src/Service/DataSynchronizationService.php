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
use App\Model\Invoices\VersionModel;
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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataSynchronizationService
{
    private const int BATCH_SIZE = 200;
    private const int MAX_RESULTS = 50;

    private array $issues = [];
    private array $workers = [];
    private array $versions = [];
    private array $epics = [];

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
        private readonly LoggerInterface $logger,
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
        $allProjectData = $service->getProjectDataCollection();
        foreach ($allProjectData->projectData as $index => $projectDatum) {
            $project = $this->projectRepository->findOneBy([
                'projectTrackerId' => $projectDatum->projectTrackerId,
                'dataProvider' => $dataProvider,
            ]);
            $dataProvider = $this->getDataProvider($dataProvider);

            if (!$project) {
                $project = new Project();
                $project->setDataProvider($dataProvider);
                $this->entityManager->persist($project);
            }

            $project->setName($projectDatum->name);
            $project->setProjectTrackerId($projectDatum->projectTrackerId);
            $project->setProjectTrackerKey($projectDatum->projectTrackerKey);
            $project->setProjectTrackerProjectUrl($projectDatum->projectTrackerProjectUrl);

            foreach ($projectDatum->versions as $versions) {
                /** @var VersionModel $versionDatum */
                foreach ($versions as $versionDatum) {
                    $version = $this->getVersion($versionDatum->id, $dataProvider);

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
            if ($dataProvider->isEnableClientSync()) {
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
                $this->clear();
            }

            $progressCallback($index, count($allProjectData->projectData));
        }

        $this->entityManager->flush();
        $this->clear();
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
                    $this->clear();
                }

                $progressCallback($index, count($allAccountData));
            }

            $this->entityManager->flush();
            $this->clear();
        }
    }

    /**
     * Synchronize issues from DataProvider.
     *
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    public function syncIssuesForProject(int $projectId, DataProvider $dataProvider, ?callable $progressCallback = null): void
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
            $dataProvider = $this->getDataProvider($dataProvider);
            $project = $this->getProject($project);

            $pagedIssueData = $service->getIssuesDataForProjectPaged($projectTrackerId, $startAt, self::MAX_RESULTS);
            $total = $pagedIssueData->total;

            foreach ($pagedIssueData->items as $issueDatum) {
                $dataProvider = $this->getDataProvider($dataProvider);
                $issue = $this->getIssue($issueDatum->projectTrackerId, $dataProvider);

                if (!$issue) {
                    $issue = new Issue();
                    $issue->setDataProvider($dataProvider);

                    $this->entityManager->persist($issue);
                }
                if (!$issueDatum->worker) {
                    $this->logger->info(sprintf('Issue %s worker is null', $issueDatum->projectTrackerId));
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
                if (LeantimeApiService::class === $dataProvider->getClass()) {
                    $issue->getVersions()->clear();
                }

                foreach ($issueDatum->versions as $versionData) {
                    $version = $this->getVersion($versionData->projectTrackerId, $dataProvider);

                    if (null !== $version) {
                        $issue->addVersion($version);
                    }
                }

                foreach ($issueDatum->epics as $epicTitle) {
                    if (empty($epicTitle)) {
                        continue;
                    }
                    $epic = $this->getEpic($epicTitle);

                    if (null === $epic) {
                        $epic = new Epic();
                        $epic->setTitle($epicTitle);
                        $this->entityManager->persist($epic);
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
            $this->clear();
        } while ($startAt < $total);

        $this->entityManager->flush();
        $this->clear();
    }

    /**
     * Synchronize worklogs from DataProvider.
     *
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    public function syncWorklogsForProject(int $projectId, DataProvider $dataProvider, ?callable $progressCallback = null): void
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
        $worklogsCount = count($worklogData->worklogData);
        foreach ($worklogData->worklogData as $worklogDatum) {
            $project = $this->getProject($project);
            $dataProvider = $this->getDataProvider($dataProvider);

            $issue = $this->getIssue($worklogDatum->projectTrackerIssueId, $dataProvider);

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
                ->setKind(BillableKindsEnum::tryFrom($worklogDatum->kind))
                ->setIssue($issue);

            if (null === $worklog->isBilled()) {
                $worklog->setIsBilled(false);
            }

            $project->addWorklog($worklog);
            // Keep the worklog.
            $worklogId = $worklog->getId();

            if (null !== $worklogId) {
                unset($worklogsToDeleteIds[$worklogId]);
            }

            if (null !== $progressCallback) {
                $progressCallback($worklogsAdded, $worklogsCount);

                ++$worklogsAdded;
            }

            $workerEmail = $worklog->getWorker();

            if ($workerEmail && filter_var($workerEmail, FILTER_VALIDATE_EMAIL)) {
                $this->getWorker($workerEmail);
            }

            // Flush and clear for each batch.
            if (0 === $worklogsAdded % self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->clear();
            }
        }

        // Remove leftover worklogs from project and remove the worklogs.
        $worklogsToDelete = $this->worklogRepository->findBy(['id' => $worklogsToDeleteIds]);
        foreach ($worklogsToDelete as $worklog) {
            $project->removeWorklog($worklog);
            $this->entityManager->remove($worklog);
        }

        $this->entityManager->flush();
        $this->clear();
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

    /**
     * Migrate from issue.epicName to issue.epics.
     *
     * @param callable|null $progressCallback
     *
     * @return void
     */
    public function migrateEpics(?callable $progressCallback = null): void
    {
        // Get all issues
        $issues = $this->issueRepository->findAll();

        if (!$issues) {
            if (null !== $progressCallback) {
                $progressCallback(0, 0);
            }
        }

        $issuesProcessed = 0;

        foreach ($issues as $issue) {
            $existingEpicNames = $issue->getEpics()->reduce(function (array $carry, Epic $epic) {
                $epicName = $epic->getTitle();
                if (null !== $epicName && !in_array($epicName, $carry)) {
                    $carry[] = $epic->getTitle();
                }

                return $carry;
            }, []);

            $epicNameArray = [];

            if (LeantimeApiService::class === $issue->getDataProvider()?->getClass()) {
                $epicNameArray = explode(',', $issue->getEpicName() ?? '');
            } elseif (!empty($issue->getEpicName())) {
                $epicNameArray[] = $issue->getEpicName();
            }

            foreach ($epicNameArray as $epicName) {
                if (empty($epicName)) {
                    continue;
                }

                $epicName = trim($epicName);

                if (in_array($epicName, $existingEpicNames, true)) {
                    continue;
                }

                $epic = $this->epicRepository->findOneBy(['title' => $epicName]);

                if (null == $epic) {
                    // Create a new Epic if it doesn't exist
                    $epic = new Epic();
                    $epic->setTitle($epicName);
                    $this->epicRepository->save($epic, true);
                }

                // Assign the Epic to the Issue
                $issue->addEpic($epic);
            }

            if (null !== $progressCallback) {
                $progressCallback($issuesProcessed, count($issues));
                ++$issuesProcessed;
            }
        }

        // Save changes to the database
        $this->entityManager->flush();
    }

    private function getWorker(string $email): Worker
    {
        if (isset($this->workers[$email])) {
            return $this->workers[$email];
        }

        $worker = $this->workerRepository->findOneBy([
            'email' => $email,
        ]);

        if (null === $worker) {
            $worker = new Worker();
            $worker->setEmail($email);
            $this->entityManager->persist($worker);
        }

        $this->workers[$email] = $worker;

        return $worker;
    }

    private function getIssue(string $projectTrackerIssueId, DataProvider $dataProvider): ?Issue
    {
        if (isset($this->issues[$projectTrackerIssueId])) {
            return $this->issues[$projectTrackerIssueId];
        }

        $issue = $this->issueRepository->findOneBy([
            'projectTrackerId' => $projectTrackerIssueId,
            'dataProvider' => $dataProvider,
        ]);

        $this->issues[$projectTrackerIssueId] = $issue;

        return $issue;
    }

    private function getVersion(string $projectTrackerId, DataProvider $dataProvider): ?Version
    {
        if (isset($this->versions[$projectTrackerId])) {
            return $this->versions[$projectTrackerId];
        }

        $version = $this->versionRepository->findOneBy([
            'projectTrackerId' => $projectTrackerId,
            'dataProvider' => $dataProvider,
        ]);

        $this->versions[$projectTrackerId] = $version;

        return $version;
    }

    private function getEpic(string $title): ?Epic
    {
        if (isset($this->epics[$title])) {
            return $this->epics[$title];
        }

        $epic = $this->epicRepository->findOneBy(['title' => $title]);

        $this->epics[$title] = $epic;

        return $epic;
    }

    private function getProject(Project $project): Project
    {
        if (!$this->entityManager->contains($project)) {
            $project = $this->projectRepository->find($project->getId());
        }

        if (null === $project) {
            throw new \RuntimeException('Project not found');
        }

        return $project;
    }

    private function getDataProvider(DataProvider $dataProvider): DataProvider
    {
        if (!$this->entityManager->contains($dataProvider)) {
            $dataProvider = $this->dataProviderRepository->find($dataProvider->getId());
        }

        if (null === $dataProvider) {
            throw new \RuntimeException('DataProvider not found');
        }

        return $dataProvider;
    }

    private function clear(): void
    {
        $this->workers = [];
        $this->issues = [];
        $this->versions = [];
        $this->epics = [];

        $this->entityManager->clear();
    }
}
