<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\BillableKindsEnum;
use App\Exception\NotFoundException;
use App\Model\DataProvider\DataProviderIssueData;
use App\Model\DataProvider\DataProviderProjectData;
use App\Model\DataProvider\DataProviderVersionData;
use App\Model\DataProvider\DataProviderWorklogData;
use App\Repository\DataProviderRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

class DataProviderService
{
    public const IMPLEMENTATIONS = [
        LeantimeApiService::class,
    ];
    public const SECONDS_IN_HOUR = 60 * 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
        private readonly IssueRepository $issueRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly VersionRepository $versionRepository,
        private readonly ContainerInterface $transportLocator,
        private readonly LoggerInterface $logger,
        protected readonly float $weekGoalLow,
        protected readonly float $weekGoalHigh,
        protected readonly string $sprintNameRegex,
    ) {
    }

    public function createDataProvider(string $name, string $class, string $url, string $secret, bool $enableClientSync = false, bool $enableAccountSync = false): DataProvider
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName($name);
        $dataProvider->setUrl($url);
        $dataProvider->setSecret($secret);
        $dataProvider->setClass($class);
        $dataProvider->setEnabled(true);
        $dataProvider->setEnableClientSync($enableClientSync);
        $dataProvider->setEnableAccountSync($enableAccountSync);

        $this->entityManager->persist($dataProvider);
        $this->entityManager->flush();

        return $dataProvider;
    }

    public function upsertProject(DataProviderProjectData $upsertProjectData): void
    {
        $dataProvider = $this->getDataProvider($upsertProjectData->dataProviderId);
        $project = $this->getProject($upsertProjectData->projectTrackerId, $dataProvider);

        if (null === $project) {
            $project = new Project();
            $project->setDataProvider($dataProvider);
            $this->entityManager->persist($project);
        } else {
            // Ignore upsert if modified date has not changed.
            if (null !== $upsertProjectData->sourceModifiedDate && $upsertProjectData->sourceModifiedDate->getTimestamp() === $project->getSourceModifiedDate()?->getTimestamp()) {
                $this->logger->info("Ignoring project {$project->getId()} update as source modified field is not changed.");

                return;
            }
        }

        $project->setName($upsertProjectData->name);
        $project->setProjectTrackerId($upsertProjectData->projectTrackerId);
        // TODO: Remove from entity:
        $project->setProjectTrackerKey($upsertProjectData->projectTrackerId);
        $project->setProjectTrackerProjectUrl($upsertProjectData->url);
        $project->setFetchDate($upsertProjectData->fetchTime);
        $project->setSourceModifiedDate($upsertProjectData->sourceModifiedDate);

        $this->entityManager->flush();
    }

    public function upsertVersion(DataProviderVersionData $upsertVersionData): void
    {
        $dataProvider = $this->getDataProvider($upsertVersionData->dataProviderId);

        $project = $this->getProject($upsertVersionData->projectTrackerProjectId, $dataProvider);

        if (null === $project) {
            throw new NotFoundException("Project $upsertVersionData->projectTrackerProjectId not found.");
        }

        $version = $this->getVersion($upsertVersionData->projectTrackerId, $dataProvider);

        if (!$version) {
            $version = new Version();
            $version->setDataProvider($dataProvider);
            $version->setProjectTrackerId($upsertVersionData->projectTrackerId);
            $version->setProject($project);

            $this->entityManager->persist($version);
        } else {
            // Ignore upsert if modified date has not changed.
            if (null !== $upsertVersionData->sourceModifiedDate && $upsertVersionData->sourceModifiedDate->getTimestamp() === $version->getSourceModifiedDate()?->getTimestamp()) {
                $this->logger->info("Ignoring version {$version->getId()} update as source modified field is not changed.");

                return;
            }
        }

        $version->setName($upsertVersionData->name);
        $version->setFetchDate($upsertVersionData->fetchTime);
        $version->setSourceModifiedDate($upsertVersionData->sourceModifiedDate);

        $this->entityManager->flush();
    }

    public function upsertIssue(DataProviderIssueData $upsertIssueData): void
    {
        $dataProvider = $this->getDataProvider($upsertIssueData->dataProviderId);
        $project = $this->getProject($upsertIssueData->projectTrackerProjectId, $dataProvider);
        $issue = $this->getIssue($upsertIssueData->projectTrackerId, $dataProvider);

        if (!$issue) {
            $issue = new Issue();
            $issue->setDataProvider($dataProvider);

            $this->entityManager->persist($issue);
        } else {
            // Ignore upsert if modified date has not changed.
            if (null !== $upsertIssueData->sourceModifiedDate && $upsertIssueData->sourceModifiedDate->getTimestamp() === $issue->getSourceModifiedDate()?->getTimestamp()) {
                $this->logger->info("Ignoring issue {$issue->getId()} update as source modified field is not changed.");

                return;
            }
        }

        $issue->setName($upsertIssueData->name);
        $issue->setProject($project);
        $issue->setProjectTrackerId($upsertIssueData->projectTrackerId);
        $issue->setProjectTrackerKey($upsertIssueData->projectTrackerId);
        $issue->setResolutionDate($upsertIssueData->resolutionDate);
        $issue->setStatus($upsertIssueData->status);
        $issue->setPlanHours($upsertIssueData->plannedHours);
        $issue->setHoursRemaining($upsertIssueData->remainingHours);
        $issue->setDueDate($upsertIssueData->dueDate);
        $issue->setWorker($upsertIssueData->worker);
        $issue->setLinkToIssue($upsertIssueData->url);
        $issue->setFetchDate($upsertIssueData->fetchTime);
        $issue->setSourceModifiedDate($upsertIssueData->sourceModifiedDate);

        $this->entityManager->flush();
    }

    public function upsertWorklog(DataProviderWorklogData $upsertWorklogData): void
    {
        $dataProvider = $this->getDataProvider($upsertWorklogData->dataProviderId);
        $issue = $this->getIssue($upsertWorklogData->projectTrackerIssueId, $dataProvider);

        if (null === $issue) {
            throw new NotFoundException('Issue not found');
        }

        $worklog = $this->getWorklog($upsertWorklogData->projectTrackerId, $dataProvider);

        if (!$worklog) {
            $worklog = new Worklog();
            $worklog->setDataProvider($dataProvider);

            $this->entityManager->persist($worklog);
        } else {
            // Ignore upsert if modified date has not changed.
            if (null !== $upsertWorklogData->sourceModifiedDate && $upsertWorklogData->sourceModifiedDate->getTimestamp() === $worklog->getSourceModifiedDate()?->getTimestamp()) {
                $this->logger->info("Ignoring worklog {$worklog->getId()} update as source modified field is not changed.");

                return;
            }
        }

        $worklog->setWorklogId($upsertWorklogData->projectTrackerId);
        $worklog->setDescription($upsertWorklogData->description);
        $worklog->setWorker($upsertWorklogData->username);
        $worklog->setStarted($upsertWorklogData->startedDate);
        $worklog->setProjectTrackerIssueId($upsertWorklogData->projectTrackerIssueId);
        $worklog->setTimeSpentSeconds($upsertWorklogData->hours * $this::SECONDS_IN_HOUR);
        $worklog->setKind(BillableKindsEnum::tryFrom($upsertWorklogData->kind));
        $worklog->setProject($issue->getProject());
        $worklog->setIssue($issue);
        $worklog->setFetchDate($upsertWorklogData->fetchTime);
        $worklog->setSourceModifiedDate($upsertWorklogData->sourceModifiedDate);

        $this->entityManager->flush();
    }

    private function getDataProvider(int $id): DataProvider
    {
        $dataProvider = $this->dataProviderRepository->find($id);

        if (null === $dataProvider) {
            throw new NotFoundException("DataProvider with id $id not found");
        }

        return $dataProvider;
    }

    private function getProject(string $projectTrackerId, DataProvider $dataProvider): ?Project
    {
        return $this->projectRepository->findOneBy([
            'projectTrackerId' => $projectTrackerId,
            'dataProvider' => $dataProvider,
        ]);
    }

    private function getVersion(string $projectTrackerId, DataProvider $dataProvider): ?Version
    {
        return $this->versionRepository->findOneBy([
            'projectTrackerId' => $projectTrackerId,
            'dataProvider' => $dataProvider,
        ]);
    }

    private function getIssue(string $projectTrackerIssueId, DataProvider $dataProvider): ?Issue
    {
        return $this->issueRepository->findOneBy([
            'projectTrackerId' => $projectTrackerIssueId,
            'dataProvider' => $dataProvider,
        ]);
    }

    private function getWorklog(int $projectTrackerId, DataProvider $dataProvider): ?Worklog
    {
        return $this->worklogRepository->findOneBy([
            'worklogId' => $projectTrackerId,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Counts the number of pending jobs for a specific queue based on the transport name.
     *
     * @param string $transportName The name of the transport (queue) to count pending jobs for
     *
     * @return int The number of pending jobs in the specified queue
     *
     * @throws ContainerExceptionInterface
     */
    public function countPendingJobsByQueueName(string $transportName): int
    {
        if (!$this->transportLocator->has($transportName)) {
            throw new \InvalidArgumentException('The transport does not exist.');
        }

        $transport = $this->transportLocator->get($transportName);
        if (!$transport instanceof MessageCountAwareInterface) {
            throw new \RuntimeException('The transport is not message count aware.');
        }

        return $transport->getMessageCount();
    }

    /**
     * Get number of failed jobs that last 24 hours.
     *
     * @return int number of failed jobs
     */
    public function countFailedJobsTheLastDay(): int
    {
        try {
            $conn = $this->entityManager->getConnection();
            $sql = 'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = "failed" AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)';
            $stmt = $conn->prepare($sql);

            $result = $stmt->execute()->fetchOne();

            if (false === $result) {
                throw new \RuntimeException('No result found.');
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return 0;
        }
    }

    public function projectRemovedFromDataProvider(int $dataProviderId, string $projectTrackerId): void
    {
        // A project can be removed if no worklogs are bound to any invoices.

        $dataProvider = $this->getDataProvider($dataProviderId);

        $project = $this->projectRepository->findOneBy(['dataProvider' => $dataProvider, 'projectTrackerId' => $projectTrackerId]);

        if (null === $project) {
            $this->logger->warning('Cannot remove project since it does not exist');

            return;
        }

        if (!$project->getInvoices()->isEmpty()) {
            $this->logger->warning('Cannot remove project since project invoices exist');

            return;
        }

        if (!$project->getIssues()->isEmpty()) {
            $this->logger->warning('Cannot remove project since project issues exist');

            return;
        }

        if (!$project->getWorklogs()->isEmpty()) {
            $this->logger->warning('Cannot remove project since project worklogs exist');

            return;
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();
    }

    public function versionRemovedFromDataProvider(int $dataProviderId, string $projectTrackerId): void
    {
        // Versions can always be removed.

        $dataProvider = $this->getDataProvider($dataProviderId);

        $version = $this->versionRepository->findOneBy(['dataProvider' => $dataProvider, 'projectTrackerId' => $projectTrackerId]);

        if (null === $version) {
            $this->logger->warning('Cannot remove version since it does not exist');

            return;
        }

        $this->entityManager->remove($version);
        $this->entityManager->flush();
    }

    public function issueRemovedFromDataProvider(int $dataProviderId, string $projectTrackerId): void
    {
        // Issues can be removed if no worklogs connected to the issue are bound to invoices.

        $dataProvider = $this->getDataProvider($dataProviderId);

        $issue = $this->issueRepository->findOneBy(['dataProvider' => $dataProvider, 'projectTrackerId' => $projectTrackerId]);

        if (null === $issue) {
            $this->logger->warning('Cannot remove issue since it does not exist');

            return;
        }

        if (!$issue->getWorklogs()->isEmpty()) {
            $this->logger->warning('Cannot remove issue worklogs attached to issue.');

            return;
        }

        $this->entityManager->remove($issue);
        $this->entityManager->flush();
    }

    public function worklogRemovedFromDataProvider(int $dataProviderId, int $projectTrackerId): void
    {
        // Worklogs can be removed if they are not bound to invoices.

        $dataProvider = $this->getDataProvider($dataProviderId);

        $worklog = $this->worklogRepository->findOneBy(['dataProvider' => $dataProvider, 'worklogId' => $projectTrackerId]);

        if (null === $worklog) {
            $this->logger->warning('Worklog does not exist. Aborting remove.');

            return;
        }

        if (null !== $worklog->getInvoiceEntry()) {
            $this->logger->warning('Cannot remove worklog since it is bound to an invoice entry.');

            return;
        }

        $this->entityManager->remove($worklog);
        $this->entityManager->flush();
    }
}
