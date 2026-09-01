<?php

namespace App\Tests\Unit\Service;

use App\Entity\DataProvider;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\Worklog;
use App\Exception\NotFoundException;
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
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Covers the fan-out layer: which messages get queued, on which transport, and
 * which entity types get scoped to the data provider's project tracker ids.
 */
class LeantimeApiServiceDispatchTest extends TestCase
{
    /** @var array<int, array{message: object, stamps: array<mixed>}> */
    private array $dispatched = [];

    private DataProviderRepository&\PHPUnit\Framework\MockObject\MockObject $dataProviderRepository;
    private ProjectRepository&\PHPUnit\Framework\MockObject\MockObject $projectRepository;
    private LeantimeApiService $service;

    protected function setUp(): void
    {
        $this->dispatched = [];

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(
            function (object $message, array $stamps = []): Envelope {
                $this->dispatched[] = ['message' => $message, 'stamps' => $stamps];

                return new Envelope($message);
            }
        );

        $this->dataProviderRepository = $this->createMock(DataProviderRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);

        $this->service = new LeantimeApiService(
            $this->createMock(HttpClientInterface::class),
            $messageBus,
            $this->dataProviderRepository,
            $this->createMock(EntityManagerInterface::class),
            $this->projectRepository,
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testUpdateOnlyTargetsEnabledLeantimeProviders(): void
    {
        $this->dataProviderRepository->expects($this->once())
            ->method('findBy')
            ->with(['class' => LeantimeApiService::class, 'enabled' => true])
            ->willReturn([]);

        $this->service->update(Project::class);

        $this->assertSame([], $this->dispatched);
    }

    public function testUpdateQueuesOneMessagePerProvider(): void
    {
        $this->givenProviders(1, 2);

        $this->service->update(Project::class);

        $this->assertCount(2, $this->dispatched);
        $this->assertSame([1, 2], array_map(
            fn (array $entry) => $this->asUpdateMessage($entry['message'])->dataProviderId,
            $this->dispatched
        ));
    }

    public function testUpdateStartsAtTheBeginningWithTheConfiguredPageSize(): void
    {
        $this->givenProviders(1);

        $this->service->update(Project::class);

        $message = $this->firstUpdateMessage();
        $this->assertSame(Project::class, $message->className);
        $this->assertSame(0, $message->start);
        $this->assertSame(100, $message->limit);
    }

    /**
     * @dataProvider unscopedClassNameProvider
     *
     * @param class-string $className
     */
    public function testProjectsAndWorkersAreNotScopedToProjectIds(string $className): void
    {
        $this->givenProviders(1);
        $this->projectRepository->expects($this->never())->method('getProjectTrackerIdsByDataProviders');

        $this->service->update($className);

        $this->assertNull($this->firstUpdateMessage()->projectTrackerProjectIds);
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function unscopedClassNameProvider(): array
    {
        return [
            'projects' => [Project::class],
            'workers' => [Worker::class],
        ];
    }

    /**
     * @dataProvider scopedClassNameProvider
     *
     * @param class-string $className
     */
    public function testOtherTypesAreScopedToTheProvidersProjectIds(string $className): void
    {
        $this->givenProviders(1);
        $this->projectRepository->expects($this->once())
            ->method('getProjectTrackerIdsByDataProviders')
            ->willReturn(['PROJ-1', 'PROJ-2']);

        $this->service->update($className);

        $this->assertSame(['PROJ-1', 'PROJ-2'], $this->firstUpdateMessage()->projectTrackerProjectIds);
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function scopedClassNameProvider(): array
    {
        return [
            'versions' => [Version::class],
            'issues' => [Issue::class],
            'worklogs' => [Worklog::class],
        ];
    }

    /**
     * @dataProvider queueProvider
     */
    public function testUpdateUsesTheQueueMatchingTheAsyncFlag(bool $async, string $expectedQueue): void
    {
        $this->givenProviders(1);

        $this->service->update(Project::class, $async);

        $this->assertSame([$expectedQueue], $this->firstStamp()->getTransportNames());
        $this->assertSame($async, $this->firstUpdateMessage()->asyncJobQueue);
    }

    /**
     * @dataProvider queueProvider
     */
    public function testDeleteUsesTheQueueMatchingTheAsyncFlag(bool $async, string $expectedQueue): void
    {
        $this->givenProviders(1);

        $this->service->delete($async);

        $this->assertSame([$expectedQueue], $this->firstStamp()->getTransportNames());
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function queueProvider(): array
    {
        return [
            'synchronous' => [false, 'sync'],
            'asynchronous' => [true, 'async'],
        ];
    }

    public function testUpdatePassesTheModifiedAfterCutoffAlong(): void
    {
        $this->givenProviders(1);
        $modifiedAfter = new \DateTime('2026-01-01');

        $this->service->update(Project::class, false, $modifiedAfter, true);

        $message = $this->firstUpdateMessage();
        $this->assertSame($modifiedAfter, $message->modifiedAfter);
        $this->assertTrue($message->disableModifiedAtCheck);
    }

    public function testUpdateAllCoversEverySynchronisedType(): void
    {
        $this->dataProviderRepository->method('findBy')->willReturn([$this->provider(1)]);
        $this->projectRepository->method('getProjectTrackerIdsByDataProviders')->willReturn([]);

        $this->service->updateAll();

        $this->assertSame(
            [Project::class, Version::class, Issue::class, Worklog::class, Worker::class],
            array_map(fn (array $entry) => $this->asUpdateMessage($entry['message'])->className, $this->dispatched)
        );
    }

    public function testDeleteQueuesEveryDeletedTypePerProvider(): void
    {
        $this->givenProviders(1, 2);
        $deletedAfter = new \DateTime('2026-02-01');

        $this->service->delete(false, $deletedAfter);

        $this->assertCount(8, $this->dispatched);
        $this->assertSame(
            [1, 1, 1, 1, 2, 2, 2, 2],
            array_map(fn (array $entry) => $this->asDeleteMessage($entry['message'])->dataProviderId, $this->dispatched)
        );

        $message = $this->firstDeleteMessage();
        $this->assertSame(0, $message->start);
        $this->assertSame(100, $message->limit);
        $this->assertSame($deletedAfter, $message->deletedAfter);
    }

    /**
     * A parent is only hard-removable once its children are gone.
     */
    public function testDeleteDispatchesChildrenBeforeParents(): void
    {
        $this->givenProviders(1);

        $this->service->delete();

        $this->assertSame(
            [
                LeantimeApiService::TIMESHEETS,
                LeantimeApiService::TICKETS,
                LeantimeApiService::MILESTONES,
                LeantimeApiService::PROJECTS,
            ],
            array_map(fn (array $entry) => $this->asDeleteMessage($entry['message'])->type, $this->dispatched)
        );
    }

    public function testDeleteAllDelegatesToDelete(): void
    {
        $this->givenProviders(1);

        $this->service->deleteAll(true);

        $this->assertCount(4, $this->dispatched);
        $this->firstDeleteMessage();
        $this->assertSame(['async'], $this->firstStamp()->getTransportNames());
    }

    public function testDeleteAllDefaultsToTheSyncTransport(): void
    {
        $this->givenProviders(1);

        $this->service->deleteAll();

        $this->assertSame(['sync'], $this->firstStamp()->getTransportNames());
    }

    public function testUpdateAsJobRejectsAnUnknownDataProvider(): void
    {
        $this->dataProviderRepository->method('find')->with(404)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('DataProvider with id: 404 not found');

        $this->service->updateAsJob(Project::class, 0, 100, 404);
    }

    public function testDeleteAsJobRejectsAnUnknownDataProvider(): void
    {
        $this->dataProviderRepository->method('find')->with(404)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('DataProvider with id: 404 not found');

        $this->service->deleteAsJob(LeantimeApiService::PROJECTS, 0, 100, 404);
    }

    private function givenProviders(int ...$ids): void
    {
        $this->dataProviderRepository->method('findBy')->willReturn(
            array_map(fn (int $id) => $this->provider($id), $ids)
        );
    }

    private function provider(int $id): DataProvider
    {
        $provider = $this->createMock(DataProvider::class);
        $provider->method('getId')->willReturn($id);
        $provider->method('getClass')->willReturn(LeantimeApiService::class);

        return $provider;
    }

    private function firstUpdateMessage(): LeantimeUpdateMessage
    {
        return $this->asUpdateMessage($this->firstMessage());
    }

    private function firstDeleteMessage(): LeantimeDeleteMessage
    {
        return $this->asDeleteMessage($this->firstMessage());
    }

    private function asDeleteMessage(object $message): LeantimeDeleteMessage
    {
        $this->assertInstanceOf(LeantimeDeleteMessage::class, $message);

        return $message;
    }

    private function asUpdateMessage(object $message): LeantimeUpdateMessage
    {
        $this->assertInstanceOf(LeantimeUpdateMessage::class, $message);

        return $message;
    }

    private function firstMessage(): object
    {
        $this->assertNotEmpty($this->dispatched, 'Expected at least one dispatched message.');

        return $this->dispatched[0]['message'];
    }

    private function firstStamp(): TransportNamesStamp
    {
        $this->assertNotEmpty($this->dispatched, 'Expected at least one dispatched message.');
        $stamp = $this->dispatched[0]['stamps'][0];
        $this->assertInstanceOf(TransportNamesStamp::class, $stamp);

        return $stamp;
    }
}
