<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotAcceptableException;
use App\Exception\NotFoundException;
use App\Message\UpsertWorklogMessage;
use App\MessageHandler\UpsertWorklogHandler;
use App\Model\DataProvider\DataProviderWorklogData;
use App\Service\DataProviderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class UpsertWorklogHandlerTest extends TestCase
{
    private function createWorklogData(): DataProviderWorklogData
    {
        return new DataProviderWorklogData(
            100, 1, 'ISS-1', 'Work', new \DateTime(), 'worker@test', 1.5, '', new \DateTime(), new \DateTime(),
        );
    }

    public function testInvokeCallsUpsertWorklog(): void
    {
        $data = $this->createWorklogData();
        $message = new UpsertWorklogMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->expects($this->once())->method('upsertWorklog')->with($data);

        $handler = new UpsertWorklogHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnRowLevelFailureThrowsUnrecoverable(): void
    {
        $message = new UpsertWorklogMessage($this->createWorklogData());

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertWorklog')->willThrowException(new NotFoundException('fail'));

        $handler = new UpsertWorklogHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    /**
     * An hour count the column cannot hold describes this one row, so it has to reach the transport
     * as unrecoverable — that is what lets the sync transport skip it and keep paging.
     */
    public function testInvokeOnUnacceptableValueThrowsUnrecoverable(): void
    {
        $message = new UpsertWorklogMessage($this->createWorklogData());

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertWorklog')->willThrowException(new NotAcceptableException('hours out of range'));

        $handler = new UpsertWorklogHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnInfrastructureFailurePropagates(): void
    {
        $message = new UpsertWorklogMessage($this->createWorklogData());

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertWorklog')->willThrowException(new \RuntimeException('the database went away'));

        $handler = new UpsertWorklogHandler($this->createMock(LoggerInterface::class), $service);

        // Unrecoverable is what LeantimeApiService reads as "bad row, carry on". This is not that.
        try {
            $handler($message);
            $this->fail('Expected the failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            $this->assertSame('the database went away', $e->getMessage());
        }
    }
}
