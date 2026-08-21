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
    // The types the deleted endpoint tracks, children before the parents they hang off. A parent is
    // only hard-removable once its children are gone: DataProviderService refuses to remove a project
    // or issue that still has any, and marks it with sourceDeletedDate instead. Nothing revisits that
    // mark, so a parent reached too early stays half-deleted for good.
    private const DELETED_TYPES = [self::TIMESHEETS, self::TICKETS, self::MILESTONES, self::PROJECTS];
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
        private readonly LeantimeUrlGenerator $urlGenerator,
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
            // One message per type, since the endpoint answers with a single type's page.
            // What keeps DELETED_TYPES in order is the sync transport, not the dispatch order:
            // deleteAll() passes asyncJobQueue false, so each handler — the removals and the
            // next-page dispatch alike — runs inline, and a type's every page is done before the
            // next type is dispatched. Fanning all four out up front is only safe under that.
            // On the async queue they would interleave, and a project could be reached while its
            // timesheets sat a page behind; that path would have to chain the types instead,
            // dispatching the next one only once the current is exhausted.
            foreach ($this::DELETED_TYPES as $type) {
                $this->messageBus->dispatch(
                    new LeantimeDeleteMessage($type, 0, $this::LIMIT, $dataProvider->getId(), $asyncJobQueue, $deletedAfter),
                    [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
                );
            }
        }
    }

    public function deleteAsJob(string $type, int $startId, int $limit, int $dataProviderId, bool $asyncJobQueue = false, ?\DateTimeInterface $deletedAfter = null): void
    {
        $dataProvider = $this->dataProviderRepository->find($dataProviderId);

        if (null === $dataProvider) {
            throw new NotFoundException("DataProvider with id: $dataProviderId not found");
        }

        $classname = match ($type) {
            self::PROJECTS => Project::class,
            self::MILESTONES => Version::class,
            self::TICKETS => Issue::class,
            self::TIMESHEETS => Worklog::class,
        };

        $params = [
            'type' => $type,
            'start' => $startId,
            'limit' => $limit,
            // The plugin reads 'deletedAfter'; under the old 'deleted' it answers 400, and under
            // any other key the timestamp is discarded and every deletion ever recorded is paged
            // through.
            'deletedAfter' => $deletedAfter?->getTimestamp(),
        ];

        // Get data from Leantime.
        $data = $this->fetchFromLeantime($dataProvider, 'deleted', $params);

        // Queue delete.
        $maxDeletionId = null;

        foreach ($data->results as $result) {
            // Tracked before anything below can skip the row: a deletion that cannot be acted
            // on still has to be paged past. The cursor is deletionId, not id — the endpoint
            // orders deletions by when they happened, not by the entity they refer to.
            if (is_numeric($result->deletionId ?? null)) {
                $deletionId = (int) $result->deletionId;
                $maxDeletionId = null === $maxDeletionId ? $deletionId : max($maxDeletionId, $deletionId);
            }

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

        // Queue next page.
        if ($data->resultsCount === $limit) {
            // A full page with nothing to advance on cannot be paged past. Stopping is visible in
            // the log; continuing would re-read the same page until the queue starves.
            if (null === $maxDeletionId || $maxDeletionId < $startId) {
                $this->logger->error(sprintf('Stopping deleted %s sync at start %d: no usable deletionId on a full page, cursor cannot advance.', $type, $startId));

                return;
            }

            $this->messageBus->dispatch(
                new LeantimeDeleteMessage($type, $maxDeletionId + 1, $limit, $dataProviderId, $asyncJobQueue, $deletedAfter),
                [new TransportNamesStamp($asyncJobQueue ? $this::QUEUE_ASYNC : $this::QUEUE_SYNC)],
            );
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
        $maxId = null;

        foreach ($data->results as $result) {
            $this->dispatchUpsertMessage($className, $result, $dataProviderId, $fetchDate, $asyncJobQueue, $dataProviderUrl, $disableModifiedAtCheck);

            // The endpoint returns rows with id >= start in ascending order, so the highest usable
            // id on the page is where the next one begins. Tracking it separately from $startId
            // keeps the input cursor intact for the guard below, and skips ids that cannot be
            // paged on: null + 1 is 1, which restarted the sync from the beginning.
            if (is_numeric($result->id ?? null)) {
                $id = (int) $result->id;
                $maxId = null === $maxId ? $id : max($maxId, $id);
            }
        }

        // Clear the entity manager in sync handling, to avoid memory issues.
        if (!$asyncJobQueue) {
            $this->entityManager->clear();
        }

        // Queue next page.
        if ($data->resultsCount === $limit) {
            // A full page with nothing to advance on cannot be paged past. Stopping is visible in
            // the log; continuing would re-read the same page until the queue starves.
            if (null === $maxId || $maxId < $startId) {
                $this->logger->error(sprintf('Stopping %s sync at start %d: no usable id on a full page, cursor cannot advance.', $className, $startId));

                return;
            }

            $this->messageBus->dispatch(
                new LeantimeUpdateMessage($className, $maxId + 1, $limit, $dataProviderId, $asyncJobQueue, $modifiedAfter, $projectTrackerProjectIds, $disableModifiedAtCheck),
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
        // API_PATH_DATA carries its own leading slash, so a provider url ending in one would give //APIData.
        $baseUrl = $this->urlGenerator->baseUrl($dataProvider->getUrl());

        return $this->httpClient->request('POST', $baseUrl.$this::API_PATH_DATA.$path, [
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
        return $this->urlGenerator->baseUrl($dataProviderUrl).'/errorpage/#/tickets/showTicket/'.$ticketId;
    }

    private function linkToProject(string $projectTrackerId, ?string $dataProviderUrl): ?string
    {
        if (null === $dataProviderUrl) {
            return null;
        }

        return $this->urlGenerator->baseUrl($dataProviderUrl).'/projects/showProject/'.$projectTrackerId;
    }
}
