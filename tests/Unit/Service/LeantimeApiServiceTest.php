<?php

namespace App\Tests\Unit\Service;

use App\Entity\DataProvider;
use App\Entity\Project;
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
 * Pagination cursor behaviour of updateAsJob().
 *
 * The cursor is what keeps the sync moving: updateAsJob() queues the next page as its last action,
 * using the ids of the rows it just saw. A cursor that fails to advance re-queues the same page
 * forever and the single worker never drains.
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
}
