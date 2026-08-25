<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotFoundException;
use App\Message\UpsertWorkerMessage;
use App\MessageHandler\UpsertWorkerHandler;
use App\Model\DataProvider\DataProviderWorkerData;
use App\Service\DataProviderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class UpsertWorkerHandlerTest extends TestCase
{
    public function testInvokeCallsUpsertWorker(): void
    {
        $data = new DataProviderWorkerData(1, 'John', 'john@test.com');
        $message = new UpsertWorkerMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->expects($this->once())->method('upsertWorker')->with($data);

        $handler = new UpsertWorkerHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnRowLevelFailureThrowsUnrecoverable(): void
    {
        $data = new DataProviderWorkerData(1, 'John', 'john@test.com');
        $message = new UpsertWorkerMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertWorker')->willThrowException(new NotFoundException('fail'));

        $handler = new UpsertWorkerHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnInfrastructureFailurePropagates(): void
    {
        $data = new DataProviderWorkerData(1, 'John', 'john@test.com');
        $message = new UpsertWorkerMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertWorker')->willThrowException(new \RuntimeException('the database went away'));

        $handler = new UpsertWorkerHandler($this->createMock(LoggerInterface::class), $service);

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
