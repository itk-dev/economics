<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Version;
use App\Enum\IssueStatusEnum;
use App\Exception\NotFoundException;
use App\Interface\DataProviderServiceInterface;
use App\Message\UpsertProjectMessage;
use App\Message\UpsertVersionMessage;
use App\Model\Upsert\UpsertProjectData;
use App\Model\Upsert\UpsertVersionData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LeantimeApiService implements DataProviderServiceInterface
{
    private const LEANTIME_TIMEZONE = 'UTC';
    private const API_PATH_DATA = '/apidata/api/';
    private const PROJECTS = 'projects';
    private const MILESTONES = 'milestones';
    private const TICKETS = 'tickets';
    private const TIMESHEETS = 'timesheets';
    private const LIMIT = 1;

    private \DateTimeZone $leantimeTimeZone;

    private const STATUS_MAPPING = [
        'NEW' => IssueStatusEnum::NEW,
        'INPROGRESS' => IssueStatusEnum::IN_PROGRESS,
        'DONE' => IssueStatusEnum::DONE,
        'NONE' => IssueStatusEnum::ARCHIVED,
    ];

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly VersionRepository $versionRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly float $weekGoalLow,
        private readonly float $weekGoalHigh,
        private readonly string $sprintNameRegex,
    ) {
        $this->leantimeTimeZone = new \DateTimeZone(self::LEANTIME_TIMEZONE);
    }

    public function update(): void
    {
        $this->updateProjects();
        $this->updateVersions();
        //$this->updateIssues();
        //$this->updateWorklogs();
    }

    public function updateVersions(bool $enableJobHandling = true): void
    {
        $dataProviders = $this->dataProviderRepository->findBy(["class" => LeantimeApiService::class, 'enabled' => true]);

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider]);
            $dataProviderId = $dataProvider->getId();
            $secret = $dataProvider->getSecret();
            $url = $dataProvider->getUrl();
            $index = 0;

            do {
                $response = $this->httpClient->request('POST', $url.$this::API_PATH_DATA.$this::MILESTONES, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'x-api-key' => $secret,
                    ],
                    "json" => [
                        "start" => $index,
                        "limit" => $this::LIMIT,
                        "projectId" => $projectTrackerProjectIds,
                        // TODO: Request modified
                    ],
                ]);

                try {
                    $data = json_decode($response->getContent(), null, 512, JSON_THROW_ON_ERROR);

                    foreach ($data->results as $result) {
                        $projectTrackerId = (string) $result->id;

                        $upsertData = new UpsertVersionData(
                            $dataProviderId,
                            $result->name,
                            $projectTrackerId,
                            (string) $result->projectId,
                        );

                        if ($enableJobHandling) {
                            $this->messageBus->dispatch(new UpsertVersionMessage($upsertData));
                        } else {
                            $this->upsertVersion($upsertData);
                        }

                        $index = $result->id;
                    }
                } catch (\Exception $e) {
                    // TODO: Log error.
                    break;
                }

                $index = $index + 1;
            } while ($data->resultsCount === $this::LIMIT);
        }
    }

    public function updateProjects(bool $enableJobHandling = true): void
    {
        $dataProviders = $this->dataProviderRepository->findBy(["class" => LeantimeApiService::class, 'enabled' => true]);

        foreach ($dataProviders as $dataProvider) {
            $index = 0;
            do {
                $response = $this->httpClient->request('POST', $dataProvider->getUrl().$this::API_PATH_DATA.$this::PROJECTS, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'x-api-key' => $dataProvider->getSecret(),
                    ],
                    "json" => [
                        "start" => $index,
                        "limit" => $this::LIMIT,
                        // TODO: Request modified
                    ],
                ]);

                try {
                    $data = json_decode($response->getContent(), null, 512, JSON_THROW_ON_ERROR);

                    foreach ($data->results as $result) {
                        $projectTrackerId = (string) $result->id;

                        $upsertData = new UpsertProjectData(
                            $dataProvider->getId(),
                            $result->name,
                            $projectTrackerId,
                            $dataProvider->getUrl() . "/errorpage#/tickets/showTicket/".$projectTrackerId,
                        );

                        if ($enableJobHandling) {
                            $this->messageBus->dispatch(new UpsertProjectMessage($upsertData));
                        } else {
                            $this->upsertProject($upsertData);
                        }

                        $index = $result->id;
                    }
                } catch (\Exception $e) {
                    // TODO: Log error.
                    break;
                }

                $index = $index + 1;
            } while ($data->resultsCount === $this::LIMIT);
        }
    }

    public function upsertProject(UpsertProjectData $upsertProjectData): void
    {
        $dataProvider = $this->dataProviderRepository->find($upsertProjectData->dataProviderId);

        if (!$dataProvider) {
            throw new NotFoundException("DataProvider with id: $upsertProjectData->dataProviderId not found");
        }

        $project = $this->projectRepository->findOneBy([
            'projectTrackerId' => $upsertProjectData->projectTrackerId, 'dataProvider' => $dataProvider,
        ]);

        if ($project === null) {
            $project = new Project();
            $project->setDataProvider($dataProvider);
            $this->entityManager->persist($project);
        }

        $project->setName($upsertProjectData->name);
        $project->setProjectTrackerId($upsertProjectData->projectTrackerId);
        // TODO: Remove from entity:
        $project->setProjectTrackerKey($upsertProjectData->projectTrackerId);
        $project->setProjectTrackerProjectUrl($dataProvider->getUrl() . "/errorpage#/tickets/showTicket/".$upsertProjectData->projectTrackerId);

        $this->entityManager->flush();
    }

    public function upsertVersion(UpsertVersionData $upsertVersionData): void
    {
        $dataProvider = $this->dataProviderRepository->find($upsertVersionData->dataProviderId);

        if ($dataProvider === null) {
            throw new NotFoundException("DataProvider with id: $upsertVersionData->dataProviderId not found");
        }

        $project = $this->projectRepository->findOneBy([
            'projectTrackerId' => $upsertVersionData->projectTrackerProjectId, 'dataProvider' => $dataProvider,
        ]);

        if ($project === null) {
            throw new NotFoundException("Project with projectTrackerId: $upsertVersionData->projectTrackerProjectId not found");
        }

        $version = $this->versionRepository->findOneBy([
            'projectTrackerId' => $upsertVersionData->projectTrackerId, 'dataProvider' => $dataProvider,
        ]);

        if (!$version) {
            $version = new Version();
            $version->setDataProvider($dataProvider);
            $version->setProjectTrackerId($upsertVersionData->projectTrackerId);
            $version->setProject($project);

            $this->entityManager->persist($version);
        }

        $version->setName($upsertVersionData->name);

        $this->entityManager->flush();
    }
}
