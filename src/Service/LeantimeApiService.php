<?php

namespace App\Service;

use App\Entity\Project;
use App\Enum\IssueStatusEnum;
use App\Exception\NotFoundException;
use App\Interface\DataProviderServiceInterface;
use App\Message\UpsertProjectMessage;
use App\Model\Upsert\UpsertProjectData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
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
        private readonly HttpClientInterface $httpClient,
        private readonly ProjectRepository $projectRepository,
        private readonly DataProviderRepository $dataProviderRepository,
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

        // Sync milestones.
        // Sync tickets.
        // Sync timesheets.
    }

    public function updateProjects(bool $handleAsJobs = true): void
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

                        if ($handleAsJobs) {
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
}
