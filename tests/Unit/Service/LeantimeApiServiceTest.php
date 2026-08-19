<?php

namespace App\Tests\Unit\Service;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Message\LeantimeDeleteMessage;
use App\Message\LeantimeUpdateMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Service\LeantimeApiService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Pagination cursor behaviour of updateAsJob() and deleteAsJob().
 *
 * The cursor is what keeps the sync moving: both queue the next page as their last action, using
 * the ids of the rows they just saw. A cursor that fails to advance re-queues the same page forever
 * and the single worker never drains.
 *
 * The two page on different columns — updateAsJob() on the entity's id, deleteAsJob() on the
 * deletion's own `deletionId` — because deletions are ordered by when they happened.
 */
class LeantimeApiServiceTest extends TestCase
{
    private const LIMIT = 100;

    /** @var list<object> */
    private array $dispatched = [];

    private LoggerInterface $logger;

    /** @var list<string> */
    private array $loggedErrors = [];

    public function testNextPageStartsAfterHighestId(): void
    {
        $service = $this->createService($this->page(range(1, self::LIMIT)));

        $service->updateAsJob(Project::class, 0, self::LIMIT, 1);

        $this->assertSame(self::LIMIT + 1, $this->nextPageMessage()->start);
    }

    /**
     * A null id on the last row used to leave $startId null, and null + 1 is 1 — the next page
     * restarted the sync from the beginning and re-queued itself forever.
     */
    public function testNullIdOnLastRowDoesNotResetCursor(): void
    {
        $ids = range(1, self::LIMIT);
        $ids[self::LIMIT - 1] = null;

        $service = $this->createService($this->page($ids));

        $service->updateAsJob(Project::class, 0, self::LIMIT, 1);

        $next = $this->nextPageMessage();
        $this->assertNotSame(1, $next->start, 'A null trailing id must not restart the sync from the beginning.');
        $this->assertSame(self::LIMIT, $next->start, 'The cursor should follow the highest usable id on the page.');
    }

    public function testDescendingIdsDoNotMoveCursorBackwards(): void
    {
        $service = $this->createService($this->page(range(self::LIMIT, 1)));

        $service->updateAsJob(Project::class, 0, self::LIMIT, 1);

        $this->assertSame(self::LIMIT + 1, $this->nextPageMessage()->start);
    }

    /**
     * With no usable id anywhere on a full page the cursor genuinely cannot advance. Stopping
     * loudly beats looping: an invisible infinite loop starves every other sync of the one worker.
     */
    public function testPageWithNoUsableIdStopsAndLogs(): void
    {
        $service = $this->createService($this->page(array_fill(0, self::LIMIT, null)));

        $service->updateAsJob(Project::class, 40, self::LIMIT, 1);

        $this->assertNull($this->findNextPageMessage(), 'No next page may be queued when the cursor cannot advance.');
        $this->assertCount(1, $this->loggedErrors);
        $this->assertStringContainsString(Project::class, $this->loggedErrors[0]);
        $this->assertStringContainsString('40', $this->loggedErrors[0]);
    }

    public function testPartialPageQueuesNoNextPage(): void
    {
        $service = $this->createService($this->page(range(1, 10)));

        $service->updateAsJob(Project::class, 0, self::LIMIT, 1);

        $this->assertNull($this->findNextPageMessage());
    }

    public function testDeletedNextPageStartsAfterHighestDeletionId(): void
    {
        $service = $this->createService($this->deletedPage(range(1, self::LIMIT)));

        $service->deleteAsJob(LeantimeApiService::TICKETS, 0, self::LIMIT, 1);

        $next = $this->nextDeletePageMessage();
        $this->assertSame(self::LIMIT + 1, $next->start);
        $this->assertSame(LeantimeApiService::TICKETS, $next->type, 'The next page has to stay on the same type.');
    }

    /**
     * The entity ids are whatever was deleted, in no particular order — paging on them would skip
     * deletions. Only deletionId is monotonic.
     */
    public function testDeletedCursorFollowsTheDeletionIdRatherThanTheEntityId(): void
    {
        $service = $this->createService($this->deletedPage(
            range(1, self::LIMIT),
            array_map(static fn (int $id) => 90000 - $id, range(1, self::LIMIT)),
        ));

        $service->deleteAsJob(LeantimeApiService::TICKETS, 0, self::LIMIT, 1);

        $this->assertSame(self::LIMIT + 1, $this->nextDeletePageMessage()->start);
    }

    /**
     * A deletion whose entity id is null is skipped, but it still occupies a place in the page, so
     * the cursor has to move past it or the next request refetches this same page.
     */
    public function testADeletionWithoutAnEntityIdStillMovesTheCursor(): void
    {
        $ids = range(1, self::LIMIT);
        $ids[self::LIMIT - 1] = null;

        $service = $this->createService($this->deletedPage(range(1, self::LIMIT), $ids));

        $service->deleteAsJob(LeantimeApiService::TICKETS, 0, self::LIMIT, 1);

        $this->assertSame(self::LIMIT + 1, $this->nextDeletePageMessage()->start);
        $this->assertCount(1, $this->loggedErrors);
    }

    public function testDeletedPageWithNoUsableDeletionIdStopsAndLogs(): void
    {
        // The entities are all identifiable; it is the deletions themselves that cannot be paged on.
        $service = $this->createService($this->deletedPage(array_fill(0, self::LIMIT, null), range(1, self::LIMIT)));

        $service->deleteAsJob(LeantimeApiService::TICKETS, 40, self::LIMIT, 1);

        $this->assertNull($this->findNextDeletePageMessage(), 'No next page may be queued when the cursor cannot advance.');
        $this->assertCount(1, $this->loggedErrors);
        $this->assertStringContainsString(LeantimeApiService::TICKETS, $this->loggedErrors[0]);
        $this->assertStringContainsString('40', $this->loggedErrors[0]);
    }

    public function testDeletedPartialPageQueuesNoNextPage(): void
    {
        $service = $this->createService($this->deletedPage(range(1, 10)));

        $service->deleteAsJob(LeantimeApiService::TICKETS, 0, self::LIMIT, 1);

        $this->assertNull($this->findNextDeletePageMessage());
    }

    /**
     * A page of project rows with the given ids. Only the cursor matters here, so every other
     * field is fixed and valid.
     *
     * @param array<int, int|null> $ids
     */
    private function page(array $ids): object
    {
        $results = array_map(static fn ($id) => (object) [
            'id' => $id,
            'name' => 'Project '.($id ?? 'null'),
            'modified' => '2026-07-30 12:00:00',
        ], array_values($ids));

        return (object) [
            'results' => $results,
            'resultsCount' => count($results),
        ];
    }

    /**
     * A page of deletions. `$entityIds` defaults to mirroring the deletion ids; pass it explicitly
     * to tell the two columns apart.
     *
     * @param array<int, int|null>      $deletionIds
     * @param array<int, int|null>|null $entityIds
     */
    private function deletedPage(array $deletionIds, ?array $entityIds = null): object
    {
        $deletionIds = array_values($deletionIds);
        $entityIds = null === $entityIds ? $deletionIds : array_values($entityIds);

        $results = array_map(static fn ($deletionId, $id) => (object) [
            'deletionId' => $deletionId,
            'id' => $id,
            'deletedDate' => '2026-07-30T12:00:00.000000Z',
        ], $deletionIds, $entityIds);

        return (object) [
            'results' => $results,
            'resultsCount' => count($results),
        ];
    }

    private function createService(object $page): LeantimeApiService
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode($page));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $this->dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(
            function (object $message, array $stamps = []): Envelope {
                $this->dispatched[] = $message;

                return new Envelope($message, $stamps);
            }
        );

        $dataProvider = new DataProvider();
        $dataProvider->setName('Test provider');
        $dataProvider->setEnabled(true);
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('http://localhost/');
        $dataProvider->setSecret('Not so secret');

        $dataProviderRepository = $this->createMock(DataProviderRepository::class);
        $dataProviderRepository->method('find')->willReturn($dataProvider);

        $this->loggedErrors = [];
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->method('error')->willReturnCallback(
            function (string $message): void {
                $this->loggedErrors[] = $message;
            }
        );

        return new LeantimeApiService(
            $httpClient,
            $messageBus,
            $dataProviderRepository,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ProjectRepository::class),
            $this->logger,
        );
    }

    private function nextPageMessage(): LeantimeUpdateMessage
    {
        $message = $this->findNextPageMessage();
        $this->assertNotNull($message, 'Expected a next-page message to be queued.');

        return $message;
    }

    private function findNextPageMessage(): ?LeantimeUpdateMessage
    {
        foreach ($this->dispatched as $message) {
            if ($message instanceof LeantimeUpdateMessage) {
                return $message;
            }
        }

        return null;
    }

    private function nextDeletePageMessage(): LeantimeDeleteMessage
    {
        $message = $this->findNextDeletePageMessage();
        $this->assertNotNull($message, 'Expected a next-page message to be queued.');

        return $message;
    }

    private function findNextDeletePageMessage(): ?LeantimeDeleteMessage
    {
        foreach ($this->dispatched as $message) {
            if ($message instanceof LeantimeDeleteMessage) {
                return $message;
            }
        }

        return null;
    }
}
