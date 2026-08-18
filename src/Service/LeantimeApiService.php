<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\Worklog;
use App\Enum\IssueStatusEnum;
use App\Exception\NotAcceptableException;
use App\Exception\NotFoundException;
use App\Interface\DataProviderInterface;
use App\Message\EntityRemovedFromDataProviderMessage;
use App\Message\LeantimeDeleteMessage;
use App\Message\LeantimeUpdateMessage;
use App\Message\UpsertIssueMessage;
use App\Message\UpsertProjectMessage;
use App\Message\UpsertVersionMessage;
use App\Message\UpsertWorkerMessage;
use App\Message\UpsertWorklogMessage;
use App\Model\DataProvider\DataProviderIssueData;
use App\Model\DataProvider\DataProviderProjectData;
use App\Model\DataProvider\DataProviderVersionData;
use App\Model\DataProvider\DataProviderWorkerData;
use App\Model\DataProvider\DataProviderWorklogData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LeantimeApiService implements DataProviderInterface
{
    private const API_PATH_DATA = '/APIData/API/';
    public const PROJECTS = 'projects';
    public const MILESTONES = 'milestones';
    public const TICKETS = 'tickets';
    public const TIMESHEETS = 'timesheets';
    public const WORKERS = 'workers';
    private const LIMIT = 100;
    // Placeholder for a null name; the name columns are not nullable, and dropping the row would
    // lose real data — for issues it would make their worklogs unstorable. The tracker id is
    // appended so two unnamed entities stay distinguishable: names are used as lookup keys
    // elsewhere, e.g. ProjectBillingService resolves a client by version name.
    private const NAME_MISSING = '(no name)';
    private const QUEUE_ASYNC = 'async';
    private const QUEUE_SYNC = 'sync';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $messageBus,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectRepository $projectRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function updateAll(bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null, bool $disableModifiedAtCheck = false): void
    {
        $this->update(Project::class, $asyncJobQueue, $modifiedAfter, $disableModifiedAtCheck);
        $this->update(Version::class, $asyncJobQueue, $modifiedAfter, $disableModifiedAtCheck);
        $this->update(Issue::class, $asyncJobQueue, $modifiedAfter, $disableModifiedAtCheck);
        $this->update(Worklog::class, $asyncJobQueue, $modifiedAfter, $disableModifiedAtCheck);
        $this->update(Worker::class, $asyncJobQueue, $modifiedAfter, $disableModifiedAtCheck);
    }

    public function deleteAll(bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null): void
    {
        $this->delete($asyncJobQueue, $modifiedAfter);
    }

    public function update(string $className, bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null, bool $disableModifiedAtCheck = false): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $projectTrackerProjectIds = match ($className) {
                Project::class, Worker::class => null,
                default => $this->projectRepository->getProjectTrackerIdsByDataProviders([$dataProvider]),
            };

            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($className, 0, $this::LIMIT, $dataProvider->getId(), $asyncJobQueue, $modifiedAfter, $projectTrackerProjectIds, $disableModifiedAtCheck),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    public function delete(bool $asyncJobQueue = false, ?\DateTimeInterface $deletedAfter = null): void
    {
        $dataProviders = $this->getEnabledLeantimeDataProviders();

        foreach ($dataProviders as $dataProvider) {
            $this->messageBus->dispatch(
                new LeantimeDeleteMessage($dataProvider->getId(), $asyncJobQueue, $deletedAfter),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    public function deleteAsJob(int $dataProviderId, bool $asyncJobQueue = false, ?\DateTimeInterface $deletedAfter = null): void
    {
        $dataProvider = $this->dataProviderRepository->find($dataProviderId);

        if (null === $dataProvider) {
            throw new NotFoundException("DataProvider with id: $dataProviderId not found");
        }

        $types = [
            self::TIMESHEETS,
            self::TICKETS,
            self::MILESTONES,
            self::PROJECTS,
        ];

        $params = [
            'types' => $types,
            // The plugin reads 'deleted'; anything else is discarded and the whole deletion
            // history is returned, which /deleted does not paginate.
            'deleted' => $deletedAfter?->getTimestamp(),
        ];

        // Get data from Leantime.
        $data = $this->fetchFromLeantime($dataProvider, 'deleted', $params);
        $results = $data->results;

        // Queue delete.
        foreach ($types as $type) {
            if (!isset($results->{$type})) {
                continue;
            }

            $classname = match ($type) {
                self::PROJECTS => Project::class,
                self::MILESTONES => Version::class,
                self::TICKETS => Issue::class,
                self::TIMESHEETS => Worklog::class,
            };

            foreach ($results->{$type} as $result) {
                // Nothing identifies the entity to remove.
                if (null === $result->id) {
                    $this->logger->error(sprintf('Skipping deleted %s entry with no id', $type));

                    continue;
                }

                $projectTrackerId = $result->id;

                // Now that the request actually filters by timestamp, an entry dropped here is
                // dropped for good; before, every run re-read the full history and healed itself.
                // So one bad entry must not take the rest of the run with it.
                try {
                    $deletedDate = $this->getLeanDateTime($result->deletedDate);

                    $this->messageBus->dispatch(
                        new EntityRemovedFromDataProviderMessage($classname, $dataProviderId, $projectTrackerId, $deletedDate),
                        [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
                    );
                } catch (HandlerFailedException|\DateMalformedStringException|\TypeError $e) {
                    if ($e instanceof HandlerFailedException) {
                        $this->rethrowUnlessRowLevel($e);
                    }

                    $this->logger->error(sprintf('Skipping deleted %s id %s: %s', $type, $projectTrackerId, $e->getMessage()));
                }
            }
        }
    }

    public function updateAsJob(string $className, int $startId, int $limit, int $dataProviderId, ?array $projectTrackerProjectIds = null, bool $asyncJobQueue = false, ?\DateTimeInterface $modifiedAfter = null, bool $disableModifiedAtCheck = false): void
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
            Worker::class => self::WORKERS,
        };

        // Get data from Leantime.
        $data = $this->fetchFromLeantime($dataProvider, $endpoint, $params);

        $fetchDate = new \DateTime();

        // Queue upsert.
        foreach ($data->results as $result) {
            $this->dispatchUpsertMessage($className, $result, $dataProviderId, $fetchDate, $asyncJobQueue, $dataProviderUrl, $disableModifiedAtCheck);

            // Pagination walks forward by id; a null would rewind the next page to the start.
            if (null !== $result->id) {
                $startId = $result->id;
            }
        }

        $startId = $startId + 1;

        // Clear the entity manager in sync handling, to avoid memory issues.
        if (!$asyncJobQueue) {
            $this->entityManager->clear();
        }

        // Queue next page.
        if ($data->resultsCount === $limit) {
            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($className, $startId, $limit, $dataProviderId, $asyncJobQueue, $modifiedAfter, $projectTrackerProjectIds, $disableModifiedAtCheck),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        }
    }

    private function dispatchUpsertMessage(string $className, object $data, int $dataProviderId, \DateTimeInterface $fetchDate, bool $asyncJobQueue = false, ?string $dataProviderUrl = null, bool $disableModifiedAtCheck = false): void
    {
        // A TypeError, not an Exception, is what a null source field mapped onto a non-nullable
        // constructor argument raises. Uncaught it escapes the row loop in updateAsJob() before the
        // next page is queued, which is how one bad row used to halt the whole sync silently.
        try {
            $message = match ($className) {
                Project::class => new UpsertProjectMessage($this->getProjectUpsertFromResult($data, $dataProviderId, $fetchDate, $dataProviderUrl, $disableModifiedAtCheck)),
                Version::class => new UpsertVersionMessage($this->getVersionUpsertFromResult($data, $dataProviderId, $fetchDate, $disableModifiedAtCheck)),
                Issue::class => new UpsertIssueMessage($this->getIssueUpsertFromResult($data, $dataProviderId, $fetchDate, $dataProviderUrl, $disableModifiedAtCheck)),
                Worklog::class => new UpsertWorklogMessage($this->getWorklogUpsertFromResult($data, $dataProviderId, $fetchDate, $disableModifiedAtCheck)),
                Worker::class => new UpsertWorkerMessage($this->getWorkerUpsertFromResult($data, $dataProviderId, $fetchDate)),
                default => null,
            };
        } catch (NotAcceptableException|\TypeError $e) {
            $this->logger->error(sprintf('Skipping %s id %s: %s', $className, $data->id ?? '?', $e->getMessage()));

            return;
        }

        if (null === $message) {
            return;
        }

        // The dispatch needs guarding too: on the sync transport the handler runs inline here, so a
        // row the handler rejects surfaces as part of this call. Only that case is skippable —
        // rethrowUnlessRowLevel() keeps a dead database or an unreachable Leantime loud.
        try {
            $this->messageBus->dispatch(
                $message,
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
        } catch (HandlerFailedException $e) {
            $this->rethrowUnlessRowLevel($e);

            $this->logger->error(sprintf('Skipping %s id %s: %s', $className, $data->id ?? '?', $e->getMessage()));
        }
    }

    /**
     * Rethrow a handler failure unless every wrapped cause describes a single unusable row.
     *
     * The handlers mark a row they cannot process as unrecoverable and let everything else through,
     * so an exception that is not unrecoverable means the failure was not about this row.
     */
    private function rethrowUnlessRowLevel(HandlerFailedException $exception): void
    {
        $causes = $exception->getWrappedExceptions(recursive: true);

        if ([] === $causes) {
            throw $exception;
        }

        foreach ($causes as $cause) {
            if (!$cause instanceof UnrecoverableMessageHandlingException) {
                throw $exception;
            }
        }
    }

    private function getProjectUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate, ?string $dataProviderUrl = null, bool $disableModifiedAtCheck = false): DataProviderProjectData
    {
        $projectTrackerId = (string) $result->id;

        return new DataProviderProjectData(
            $dataProviderId,
            $result->name ?? $this->missingName($projectTrackerId),
            $projectTrackerId,
            $this->linkToProject($projectTrackerId, $dataProviderUrl),
            $fetchDate,
            $this->getLeanDateTime($result->modified),
            $disableModifiedAtCheck,
        );
    }

    private function getVersionUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate, bool $disableModifiedAtCheck = false): DataProviderVersionData
    {
        // A version cannot exist without a project; Version::$project is not nullable.
        if (null === $result->projectId) {
            throw new NotAcceptableException('Version upsert not acceptable: projectId is null');
        }

        return new DataProviderVersionData(
            $dataProviderId,
            $result->name ?? $this->missingName((string) $result->id),
            (string) $result->id,
            (string) $result->projectId,
            $fetchDate,
            $this->getLeanDateTime($result->modified),
            $disableModifiedAtCheck,
        );
    }

    private function getIssueUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate, ?string $dataProviderUrl = null, bool $disableModifiedAtCheck = false): DataProviderIssueData
    {
        // An issue cannot exist without a project; casting a null projectId to '' would look up no
        // project and silently clear the association an existing issue already has, taking its
        // worklogs' project with it.
        if (null === $result->projectId) {
            throw new NotAcceptableException('Issue upsert not acceptable: projectId is null');
        }

        $projectTrackerId = (string) $result->id;

        return new DataProviderIssueData(
            $projectTrackerId,
            $dataProviderId,
            (string) $result->projectId,
            $result->name ?? $this->missingName($projectTrackerId),
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
            $result->milestoneId,
            $disableModifiedAtCheck,
        );
    }

    private function getWorklogUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate, bool $disableModifiedAtCheck = false): DataProviderWorklogData
    {
        $startedDate = $this->getLeanDateTime($result->workDate);

        if (null === $startedDate) {
            throw new NotAcceptableException('Worklog upsert not acceptable: startedDate is null');
        }

        // A worklog cannot exist without an issue; Worklog::$issue is not nullable.
        if (null === $result->ticketId) {
            throw new NotAcceptableException('Worklog upsert not acceptable: ticketId is null');
        }

        // The id is the key the worklog is stored and looked up under.
        if (null === $result->id) {
            throw new NotAcceptableException('Worklog upsert not acceptable: id is null');
        }

        // A null username means the join found no user row, as Leantime never stores a null one.
        // The hours are still real, so keep the worklog and name the departed user.
        $username = $result->username ?? null;
        $usernameIsPlaceholder = null === $username;
        $username ??= 'deleted-user-'.($result->userId ?? 'unknown');

        return new DataProviderWorklogData(
            $result->id,
            $dataProviderId,
            (string) $result->ticketId,
            $result->description,
            $startedDate,
            $username,
            $result->hours,
            $result->kind,
            $fetchDate,
            $this->getLeanDateTime($result->modified),
            $disableModifiedAtCheck,
            $usernameIsPlaceholder,
        );
    }

    private function getWorkerUpsertFromResult(object $result, int $dataProviderId, \DateTimeInterface $fetchDate): DataProviderWorkerData
    {
        // The email is the key workers are matched on, and the name column is not nullable.
        if (null === $result->email) {
            throw new NotAcceptableException('Worker upsert not acceptable: email is null');
        }

        return new DataProviderWorkerData(
            $result->id,
            $result->name ?? $this->missingName((string) $result->id),
            $result->email,
        );
    }

    private function missingName(string $projectTrackerId): string
    {
        return sprintf('%s %s', self::NAME_MISSING, $projectTrackerId);
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

    private function convertStatusToEnum(?string $statusString): IssueStatusEnum
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
