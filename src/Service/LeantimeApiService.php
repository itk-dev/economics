<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Enum\IssueStatusEnum;
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
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
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
    private const LIMIT = 1;
    private const QUEUE_ASYNC = 'async';
    private const QUEUE_SYNC = 'sync';

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $messageBus,
        private readonly IssueRepository $issueRepository,
    ) {}

    public function updateAll(bool $asyncJobQueue = true): void
    {
        $this->updateProjects($asyncJobQueue);
        $this->updateVersions($asyncJobQueue);
        $this->updateIssues($asyncJobQueue);
        $this->updateWorklogs($asyncJobQueue);
    }

    public function updateProjects(bool $asyncJobQueue = false): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $this->updateProjectsAsJob(0, $this::LIMIT, $dataProvider, $asyncJobQueue);
        }
    }

    public function updateProjectsAsJob($startId, $limit, DataProvider $dataProvider, bool $asyncJobQueue = false): void
    {
        $data = $this->fetchFromLeantime($dataProvider, $this::PROJECTS, [
            "start" => $startId,
            "limit" => $limit,
            // TODO: Request modified
        ]);

        foreach ($data->results as $result) {
            $upsertData = $this->getProjectUpsertFromResult($result, $dataProvider);

            $this->messageBus->dispatch(
                new UpsertProjectMessage($upsertData),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );

            $startId = $result->id;
        }

        $startId = $startId + 1;

        // Queue next page.
        if ($data->resultsCount === $limit) {
            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($this::PROJECTS, $startId, $limit, $dataProvider, $asyncJobQueue),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    public function updateVersions(bool $asyncJobQueue = false): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider]);

            $this->updateVersionsAsJob(0, $this::LIMIT, $dataProvider, $projectTrackerProjectIds);
        }
    }

    public function updateVersionsAsJob($startId, $limit, DataProvider $dataProvider, array $projectTrackerProjectIds, bool $asyncJobQueue = false): void
    {
        $data = $this->fetchFromLeantime($dataProvider, $this::MILESTONES, [
            "start" => $startId,
            "limit" => $limit,
            "projectIds" => $projectTrackerProjectIds,
            // TODO: Request modified
        ]);

        foreach ($data->results as $result) {
            $upsertData = $this->getVersionUpsertFromResult($result, $dataProvider);

            $this->messageBus->dispatch(
                new UpsertVersionMessage($upsertData),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );

            $startId = $result->id;
        }

        $startId = $startId + 1;

        // Queue next page.
        if ($data->resultsCount === $limit) {
            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($this::MILESTONES, $startId, $limit, $dataProvider, $asyncJobQueue, $projectTrackerProjectIds),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    public function updateIssues(bool $asyncJobQueue = false): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider]);

            $this->updateIssuesAsJob(0, $this::LIMIT, $dataProvider, $projectTrackerProjectIds, $asyncJobQueue);
        }
    }

    public function updateIssuesAsJob($startId, $limit, DataProvider $dataProvider, array $projectTrackerProjectIds, bool $asyncJobQueue = false): void
    {
        $data = $this->fetchFromLeantime($dataProvider, $this::TICKETS, [
            "start" => $startId,
            "limit" => $limit,
            "projectIds" => $projectTrackerProjectIds,
            // TODO: Request modified
        ]);

        foreach ($data->results as $result) {
            $upsertData = $this->getIssueUpsertFromResult($result, $dataProvider);

            $this->messageBus->dispatch(
                new UpsertIssueMessage($upsertData),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );

            $startId = $result->id;
        }

        $startId = $startId + 1;

        // Queue next page.
        if ($data->resultsCount === $limit) {
            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($this::TICKETS, $startId, $limit, $dataProvider, $asyncJobQueue, $projectTrackerProjectIds),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    public function updateWorklogs(bool $asyncJobQueue = false): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerTicketIds = $this->issueRepository->getProjectTrackerIdsByDataProviders([$dataProvider]);

            $this->updateWorklogsAsJob(0, $this::LIMIT, $dataProvider, $projectTrackerTicketIds, $asyncJobQueue);
        }
    }

    public function updateWorklogsAsJob($startId, $limit, DataProvider $dataProvider, array $projectTrackerTicketIds, bool $asyncJobQueue = false): void
    {
        $data = $this->fetchFromLeantime($dataProvider, $this::TIMESHEETS, [
            "start" => $startId,
            "limit" => $this::LIMIT,
            "ticketIds" => $projectTrackerTicketIds,
            // TODO: Request modified
        ]);

        foreach ($data->results as $result) {
            $upsertData = $this->getWorklogUpsertFromResult($result, $dataProvider);

            $this->messageBus->dispatch(
                new UpsertWorklogMessage($upsertData),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );

            $startId = $result->id;
        }

        $startId = $startId + 1;

        // Queue next page.
        if ($data->resultsCount === $limit) {
            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($this::TIMESHEETS, $startId, $limit, $dataProvider, $asyncJobQueue, null, $projectTrackerTicketIds),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    private function getProjectUpsertFromResult(object $result, DataProvider $dataProvider): UpsertProjectData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertProjectData(
            $dataProvider->getId(),
            $result->name,
            $projectTrackerId,
            // Error page is the fastest to load.
            "TODO/".$projectTrackerId,
        );
    }

    private function getVersionUpsertFromResult(object $result, DataProvider $dataProvider): UpsertVersionData
    {
        $projectTrackerId = (string) $result->id;

        return new UpsertVersionData(
            $dataProvider->getId(),
            $result->name,
            $projectTrackerId,
            (string) $result->projectId,
        );
    }

    private function getIssueUpsertFromResult(object $result, DataProvider $dataProvider): UpsertIssueData
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
        );
    }

    private function getWorklogUpsertFromResult(object $result, DataProvider $dataProvider): UpsertWorklogData
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
