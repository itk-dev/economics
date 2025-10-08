<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\IssueStatusEnum;
use App\Interface\DataProviderInterface;
use App\Interface\FetchDateInterface;
use App\Message\LeantimeUpdateMessage;
use App\Message\UpsertIssueMessage;
use App\Message\UpsertProjectMessage;
use App\Message\UpsertVersionMessage;
use App\Message\UpsertWorklogMessage;
use App\Model\Upsert\UpsertIssueData;
use App\Model\Upsert\UpsertProjectData;
use App\Model\Upsert\UpsertVersionData;
use App\Model\Upsert\UpsertWorklogData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LeantimeApiService implements DataProviderInterface
{
    private const API_PATH_DATA = '/apidata/api/';
    public const PROJECTS = 'projects';
    public const MILESTONES = 'milestones';
    public const TICKETS = 'tickets';
    public const TIMESHEETS = 'timesheets';
    private const LIMIT = 100;
    private const QUEUE_ASYNC = 'async';
    private const QUEUE_SYNC = 'sync';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $messageBus,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
    ) {}

    public function updateAll(bool $asyncJobQueue = false, bool $modified = false): void
    {
        $this->update(Project::class, $asyncJobQueue, $modified);
        $this->update(Version::class, $asyncJobQueue, $modified);
        $this->update(Issue::class, $asyncJobQueue, $modified);
        $this->update(Worklog::class, $asyncJobQueue, $modified);
    }

    public function update(string $className, bool $asyncJobQueue = false, bool $modified = false): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = match ($className) {
                Project::class => null,
                default => $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider])
            };

            $this->updateAsJob($className,0, $this::LIMIT, $dataProvider, $projectTrackerProjectIds, $asyncJobQueue, $modified);
        }
    }

    public function updateAsJob(string $className, int $startId, int $limit, DataProvider $dataProvider, ?array $projectTrackerProjectIds = null, bool $asyncJobQueue = false, bool $modified = false): void
    {
        $params = [
            "start" => $startId,
            "limit" => $limit,
        ];

        if ($projectTrackerProjectIds !== null) {
            $params['projectIds'] = $projectTrackerProjectIds;
        }

        if ($modified) {
            $repository = $this->entityManager->getRepository($className);
            if ($repository instanceof FetchDateInterface) {
                $params['modified'] = $repository->getOldestFetchTime($dataProvider)?->getTimestamp();
            }
        }

        $endpoint = match ($className) {
            Project::class => self::PROJECTS,
            Version::class => self::MILESTONES,
            Issue::class => self::TICKETS,
            Worklog::class => self::TIMESHEETS,
        };

        // Get data from Leantime.
        $data = $this->fetchFromLeantime($dataProvider, $endpoint, $params);

        $fetchDate = new \DateTime();

        // Queue upsert.
        foreach ($data->results as $result) {
            $this->dispatchUpsertMessage($className, $result, $dataProvider, $fetchDate, $asyncJobQueue);
            $startId = $result->id;
        }

        $startId = $startId + 1;

        // Queue next page.
        if ($data->resultsCount === $limit) {
            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($className, $startId, $limit, $dataProvider, $asyncJobQueue, $modified, $projectTrackerProjectIds),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    private function dispatchUpsertMessage(string $className, object $data, DataProvider $dataProvider, \DateTimeInterface $fetchDate, bool $asyncJobQueue = false): void
    {
        match ($className) {
            Project::class => $message = new UpsertProjectMessage($this->getProjectUpsertFromResult($data, $dataProvider, $fetchDate)),
            Version::class => $message = new UpsertVersionMessage($this->getVersionUpsertFromResult($data, $dataProvider, $fetchDate)),
            Issue::class => $message = new UpsertIssueMessage($this->getIssueUpsertFromResult($data, $dataProvider, $fetchDate)),
            Worklog::class => $message = new UpsertWorklogMessage($this->getWorklogUpsertFromResult($data, $dataProvider, $fetchDate)),
        };

        $this->messageBus->dispatch(
            $message,
            [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
        );
    }

    private function getProjectUpsertFromResult(object $result, DataProvider $dataProvider, \DateTimeInterface $fetchDate): UpsertProjectData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertProjectData(
            $dataProvider->getId(),
            $result->name,
            $projectTrackerId,
            // Error page is the fastest to load.
            "TODO/".$projectTrackerId,
            $fetchDate,
        );
    }

    private function getVersionUpsertFromResult(object $result, DataProvider $dataProvider, \DateTimeInterface $fetchDate): UpsertVersionData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertVersionData(
            $dataProvider->getId(),
            $result->name,
            $projectTrackerId,
            (string) $result->projectId,
            $fetchDate,
        );
    }

    private function getIssueUpsertFromResult(object $result, DataProvider $dataProvider, \DateTimeInterface $fetchDate): UpsertIssueData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertIssueData(
            $projectTrackerId,
            $dataProvider->getId(),
            (string) $result->projectId,
            $result->name,
            $result->tags,
            $result->plannedHours,
            $result->remainingHours,
            $result->worker,
            $this->convertStatusToEnum($result->status),
            $result->dueDate !== null ? $this->getLeanDateTime($result->dueDate) : null,
            $result->resolutionDate !== null ? $this->getLeanDateTime($result->resolutionDate) : null,
            $fetchDate,
        );
    }

    private function getWorklogUpsertFromResult(object $result, DataProvider $dataProvider, \DateTimeInterface $fetchDate): UpsertWorklogData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertWorklogData(
            $projectTrackerId,
            $dataProvider->getId(),
            (string) $result->ticketId,
            $result->description,
            $result->workDate !== null ? $this->getLeanDateTime($result->workDate) : null,
            $result->username,
            $result->hours,
            $result->kind,
            $fetchDate,
        );
    }

    private function fetchFromLeantime(DataProvider $dataProvider, string $type, array $params): object
    {
        $response = $this->post($dataProvider, $type, $params);
        return json_decode($response->getContent(), null, 512, JSON_THROW_ON_ERROR);
    }

    private function post(DataProvider $dataProvider,  $path, array $body): ResponseInterface
    {
        return $this->httpClient->request('POST', $dataProvider->getUrl().$this::API_PATH_DATA.$path, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $dataProvider->getSecret(),
            ],
            "json" => $body,
        ]);
    }

    private function getLeanDateTime(string $dateString): ?\DateTimeInterface
    {
        return new \DateTime($dateString, new \DateTimeZone('UTC'));
    }

    private function convertStatusToEnum(string $statusString): IssueStatusEnum
    {
        return match ($statusString) {
            'NEW' => IssueStatusEnum::NEW,
            'INPROGRESS' => IssueStatusEnum::IN_PROGRESS,
            'DONE' => IssueStatusEnum::DONE,
            'NONE' => IssueStatusEnum::ARCHIVED,
            default => IssueStatusEnum::OTHER,
        };
    }

    private function getEnabledLeantimeDataProviders(): array
    {
        return $this->dataProviderRepository->findBy(["class" => LeantimeApiService::class, 'enabled' => true]);
    }
}
