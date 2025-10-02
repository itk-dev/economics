<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Enum\IssueStatusEnum;
use App\Interface\DataProviderInterface;
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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LeantimeApiService implements DataProviderInterface
{
    private const API_PATH_DATA = '/apidata/api/';
    private const PROJECTS = 'projects';
    private const MILESTONES = 'milestones';
    private const TICKETS = 'tickets';
    private const TIMESHEETS = 'timesheets';
    private const LIMIT = 100;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly DataProviderService $dataProviderService,
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly IssueRepository $issueRepository,
    ) {}

    public function updateAll(bool $enableJobHandling = true): void
    {
        $this->updateProjects($enableJobHandling);
        $this->updateVersions($enableJobHandling);
        $this->updateIssues($enableJobHandling);
        $this->updateWorklogs($enableJobHandling);
    }

    public function updateProjects(bool $enableJobHandling = true): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $index = 0;
            do {
                try {
                    $data = $this->fetchFromLeantime($dataProvider, $this::PROJECTS, [
                        "start" => $index,
                        "limit" => $this::LIMIT,
                        // TODO: Request modified
                    ]);

                    foreach ($data->results as $result) {
                        $upsertData = $this->getProjectUpsertFromResult($result, $dataProvider);

                        if ($enableJobHandling) {
                            $this->logger->info("Queuing Project Message.");
                            $this->messageBus->dispatch(new UpsertProjectMessage($upsertData));
                        } else {
                            $this->dataProviderService->upsertProject($upsertData);
                        }

                        $index = $result->id;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error($e->getMessage());
                    break;
                }

                $index = $index + 1;
            } while ($data->resultsCount === $this::LIMIT);
        }
    }

    public function updateVersions(bool $enableJobHandling = true): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider]);
            $index = 0;

            do {
                try {
                    $data = $this->fetchFromLeantime($dataProvider, $this::MILESTONES, [
                        "start" => $index,
                        "limit" => $this::LIMIT,
                        "projectIds" => $projectTrackerProjectIds,
                        // TODO: Request modified
                    ]);

                    foreach ($data->results as $result) {
                        $upsertData = $this->getVersionUpsertFromResult($result, $dataProvider);

                        if ($enableJobHandling) {
                            $this->logger->info("Queuing Version Message.");
                            $this->messageBus->dispatch(new UpsertVersionMessage($upsertData));
                        } else {
                            $this->dataProviderService->upsertVersion($upsertData);
                        }

                        $index = $result->id;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error($e->getMessage());
                    break;
                }

                $index = $index + 1;
            } while ($data->resultsCount === $this::LIMIT);
        }
    }

    public function updateIssues(bool $enableJobHandling = true): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider]);
            $index = 0;

            do {
                try {
                    $data = $this->fetchFromLeantime($dataProvider, $this::TICKETS, [
                        "start" => $index,
                        "limit" => $this::LIMIT,
                        "projectIds" => $projectTrackerProjectIds,
                        // TODO: Request modified
                    ]);

                    foreach ($data->results as $result) {
                        $upsertData = $this->getIssueUpsertFromResult($result, $dataProvider);

                        if ($enableJobHandling) {
                            $this->logger->info("Queuing Issue Message.");
                            $this->messageBus->dispatch(new UpsertIssueMessage($upsertData));
                        } else {
                            $this->dataProviderService->upsertIssue($upsertData);
                        }

                        $index = $result->id;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error($e->getMessage());
                    break;
                }

                $index = $index + 1;
            } while ($data->resultsCount === $this::LIMIT);
        }
    }

    public function updateWorklogs(bool $enableJobHandling = true): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerTicketIds = $this->issueRepository->getProjectTrackerIdsByDataProviders([$dataProvider]);
            $index = 0;

            do {
                try {
                    $data = $this->fetchFromLeantime($dataProvider, $this::TIMESHEETS, [
                        "start" => $index,
                        "limit" => $this::LIMIT,
                        "ticketIds" => $projectTrackerTicketIds,
                        // TODO: Request modified
                    ]);

                    foreach ($data->results as $result) {
                        $upsertData = $this->getWorklogUpsertFromResult($result, $dataProvider);

                        if ($enableJobHandling) {
                            $this->logger->info("Queuing Worklog Message.");
                            $this->messageBus->dispatch(new UpsertWorklogMessage($upsertData));
                        } else {
                            $this->dataProviderService->upsertWorklog($upsertData);
                        }

                        $index = $result->id;
                    }
                } catch (\Throwable $e) {
                    $this->logger->error($e->getMessage());
                    break;
                }

                $index = $index + 1;
            } while ($data->resultsCount === $this::LIMIT);
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
