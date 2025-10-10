<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\IssueStatusEnum;
use App\Exception\NotFoundException;
use App\Interface\DataProviderInterface;
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
    ) {
    }

    public function updateAll(bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void
    {
        $this->update(Project::class, $asyncJobQueue, $modifiedAfter);
        $this->update(Version::class, $asyncJobQueue, $modifiedAfter);
        $this->update(Issue::class, $asyncJobQueue, $modifiedAfter);
        $this->update(Worklog::class, $asyncJobQueue, $modifiedAfter);
    }

    public function update(string $className, bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = match ($className) {
                Project::class => null,
                default => $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider]),
            };

            $this->updateAsJob($className, 0, $this::LIMIT, $dataProvider->getId(), $projectTrackerProjectIds, $asyncJobQueue, $modifiedAfter);
        }
    }

    public function updateAsJob(string $className, int $startId, int $limit, int $dataProviderId, ?array $projectTrackerProjectIds = null, bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void
    {
        $dataProvider = $this->dataProviderRepository->find($dataProviderId);

        if (null === $dataProvider) {
            throw new NotFoundException("DataProvider with id: $dataProviderId not found");
        }

        $dataProviderUrl = $dataProvider->getUrl();

        $params = [
            'start' => $startId,
            'limit' => $limit,
        ];

        if (null !== $projectTrackerProjectIds) {
            $params['projectIds'] = $projectTrackerProjectIds;
        }

        if (null !== $modifiedAfter) {
            $params['modifiedAfter'] = $modifiedAfter->getTimestamp();
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
            $this->dispatchUpsertMessage($className, $result, $dataProviderId, $fetchDate, $asyncJobQueue, $dataProviderUrl);
            $startId = $result->id;
        }

        $startId = $startId + 1;

        // Clear the entity manager in sync handling, to avoid memory issues.
        if (!$asyncJobQueue) {
            $this->entityManager->clear();
        }

        // Queue next page.
        if ($data->resultsCount === $limit) {
            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($className, $startId, $limit, $dataProviderId, $asyncJobQueue, $modifiedAfter, $projectTrackerProjectIds),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    private function dispatchUpsertMessage(string $className, object $data, int $dataProviderId, \DateTimeInterface $fetchDate, bool $asyncJobQueue = false, ?string $dataProviderUrl = null): void
    {
        $message = match ($className) {
            Project::class => new UpsertProjectMessage($this->getProjectUpsertFromResult($data, $dataProviderId, $fetchDate, $dataProviderUrl)),
            Version::class => new UpsertVersionMessage($this->getVersionUpsertFromResult($data, $dataProviderId, $fetchDate)),
            Issue::class => new UpsertIssueMessage($this->getIssueUpsertFromResult($data, $dataProviderId, $fetchDate, $dataProviderUrl)),
            Worklog::class => new UpsertWorklogMessage($this->getWorklogUpsertFromResult($data, $dataProviderId, $fetchDate)),
            default => null,
        };

        if (null !== $message) {
            $this->messageBus->dispatch(
                $message,
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    private function getProjectUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate, ?string $dataProviderUrl = null): UpsertProjectData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertProjectData(
            $dataProviderId,
            $result->name,
            $projectTrackerId,
            $this->linkToProject($projectTrackerId, $dataProviderUrl),
            $fetchDate,
            $this->getLeanDateTime($result->modified),
        );
    }

    private function getVersionUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate): UpsertVersionData
    {
        return new UpsertVersionData(
            $dataProviderId,
            $result->name,
            (string) $result->id,
            (string) $result->projectId,
            $fetchDate,
            $this->getLeanDateTime($result->modified),
        );
    }

    private function getIssueUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate, ?string $dataProviderUrl = null): UpsertIssueData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertIssueData(
            $projectTrackerId,
            $dataProviderId,
            (string) $result->projectId,
            $result->name,
            $result->tags,
            $result->plannedHours,
            $result->remainingHours,
            $result->worker,
            $this->convertStatusToEnum($result->status),
            $this->getLeanDateTime($result->dueDate),
            $this->getLeanDateTime($result->resolutionDate),
            $fetchDate,
            $this->linkToTicket($projectTrackerId, $dataProviderUrl),
            $this->getLeanDateTime($result->modified),
        );
    }

    private function getWorklogUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate): UpsertWorklogData
    {
        return new UpsertWorklogData(
            $result->id,
            $dataProviderId,
            (string) $result->ticketId,
            $result->description,
            $this->getLeanDateTime($result->workDate),
            $result->username,
            $result->hours,
            $result->kind,
            $fetchDate,
            $this->getLeanDateTime($result->modified),
        );
    }

    private function fetchFromLeantime(DataProvider $dataProvider, string $type, array $params): object
    {
        $response = $this->post($dataProvider, $type, $params);

        return json_decode($response->getContent(), null, 512, JSON_THROW_ON_ERROR);
    }

    private function post(DataProvider $dataProvider, $path, array $body): ResponseInterface
    {
        return $this->httpClient->request('POST', $dataProvider->getUrl().$this::API_PATH_DATA.$path, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $dataProvider->getSecret(),
            ],
            'json' => $body,
        ]);
    }

    private function getLeanDateTime(?string $dateString): ?\DateTimeInterface
    {
        if (null === $dateString) {
            return null;
        }

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
        return $this->dataProviderRepository->findBy(['class' => LeantimeApiService::class, 'enabled' => true]);
    }

    private function linkToTicket(string $ticketId, ?string $dataProviderUrl): ?string
    {
        if (null === $dataProviderUrl) {
            return null;
        }

        // Error page is the fastest to load.
        return $dataProviderUrl.'/errorpage/#/tickets/showTicket/'.$ticketId;
    }

    private function linkToProject(string $projectTrackerId, ?string $dataProviderUrl): ?string
    {
        if (null === $dataProviderUrl) {
            return null;
        }

        return $dataProviderUrl.'/projects/showProject/'.$projectTrackerId;
    }
}
