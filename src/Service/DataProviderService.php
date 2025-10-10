<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\BillableKindsEnum;
use App\Exception\EconomicsException;
use App\Exception\NotFoundException;
use App\Exception\UnsupportedDataProviderException;
use App\Interface\DataProviderInterface;
use App\Model\Upsert\UpsertIssueData;
use App\Model\Upsert\UpsertProjectData;
use App\Model\Upsert\UpsertVersionData;
use App\Model\Upsert\UpsertWorklogData;
use App\Repository\DataProviderRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DataProviderService
{
    public const IMPLEMENTATIONS = [
        LeantimeApiService::class,
    ];
    const SECONDS_IN_HOUR = 60 * 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        protected readonly HttpClientInterface $httpClient,
        private readonly ProjectRepository $projectRepository,
        private readonly IssueRepository $issueRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly VersionRepository $versionRepository,
        protected readonly array $customFieldMappings,
        protected readonly string $defaultBoard,
        protected readonly float $weekGoalLow,
        protected readonly float $weekGoalHigh,
        protected readonly string $sprintNameRegex,
        protected readonly int $httpClientRetryDelayMs = 1000,
        protected readonly int $httpClientMaxRetries = 3,
    )
    {
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

    public function upsertProject(UpsertProjectData $upsertProjectData): void
    {
        $dataProvider = $this->getDataProvider($upsertProjectData->dataProviderId);
        $project = $this->getProject($upsertProjectData->projectTrackerId, $dataProvider);

        if ($project === null) {
            $project = new Project();
            $project->setDataProvider($dataProvider);
            $this->entityManager->persist($project);
        }

        $project->setName($upsertProjectData->name);
        $project->setProjectTrackerId($upsertProjectData->projectTrackerId);
        // TODO: Remove from entity:
        $project->setProjectTrackerKey($upsertProjectData->projectTrackerId);
        $project->setProjectTrackerProjectUrl($dataProvider->getUrl() . "/projects/showProject/" . $upsertProjectData->projectTrackerId);
        $project->setFetchTime($upsertProjectData->fetchTime);

        $this->entityManager->flush();
    }

    public function upsertVersion(UpsertVersionData $upsertVersionData): void
    {
        $dataProvider = $this->getDataProvider($upsertVersionData->dataProviderId);
        $project = $this->getProject($upsertVersionData->projectTrackerProjectId, $dataProvider);

        $version = $this->getVersion($upsertVersionData->projectTrackerId, $dataProvider);

        if (!$version) {
            $version = new Version();
            $version->setDataProvider($dataProvider);
            $version->setProjectTrackerId($upsertVersionData->projectTrackerId);
            $version->setProject($project);

            $this->entityManager->persist($version);
        }

        $version->setName($upsertVersionData->name);
        $version->setFetchTime($upsertVersionData->fetchTime);

        $this->entityManager->flush();
    }

    public function upsertIssue(UpsertIssueData $upsertIssueData): void
    {
        $dataProvider = $this->getDataProvider($upsertIssueData->dataProviderId);
        $project = $this->getProject($upsertIssueData->projectTrackerProjectId, $dataProvider);
        $issue = $this->getIssue($upsertIssueData->projectTrackerId, $dataProvider);

        if (!$issue) {
            $issue = new Issue();
            $issue->setDataProvider($dataProvider);

            $this->entityManager->persist($issue);
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
        $issue->setFetchTime($upsertIssueData->fetchTime);

        $this->entityManager->flush();
    }

    public function upsertWorklog(UpsertWorklogData $upsertWorklogData): void
    {
        $dataProvider = $this->getDataProvider($upsertWorklogData->dataProviderId);
        $issue = $this->getIssue($upsertWorklogData->projectTrackerIssueId, $dataProvider);
        $worklog = $this->getWorklog($upsertWorklogData->projectTrackerId, $dataProvider);

        if (!$worklog) {
            $worklog = new Worklog();
            $worklog->setDataProvider($dataProvider);

            $this->entityManager->persist($worklog);
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
        $worklog->setFetchTime($upsertWorklogData->fetchTime);

        $this->entityManager->flush();
    }

    private function getDataProvider(int $id): DataProvider
    {
        $dataProvider = $this->dataProviderRepository->find($id);

        if ($dataProvider === null) {
            throw new NotFoundException("DataProvider with id $id not found");
        }

        return $dataProvider;
    }

    private function getProject(int $projectTrackerId, DataProvider $dataProvider): ?Project
    {
        return $this->projectRepository->findOneBy([
            'projectTrackerId' => $projectTrackerId,
            'dataProvider' => $dataProvider,
        ]);
    }

    private function getVersion(int $projectTrackerId, DataProvider $dataProvider): ?Version
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

    private function getWorklog(string $projectTrackerId, DataProvider $dataProvider): ?Worklog
    {
        return $this->worklogRepository->findOneBy([
            'worklogId' => $projectTrackerId,
            'dataProvider' => $dataProvider,
        ]);
    }
}
