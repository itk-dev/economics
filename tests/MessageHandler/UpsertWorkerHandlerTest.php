<?php

namespace App\Tests\MessageHandler;

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

    public function testInvokeOnExceptionThrowsUnrecoverable(): void
    {
        $data = new DataProviderWorkerData(1, 'John', 'john@test.com');
        $message = new UpsertWorkerMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertWorker')->willThrowException(new \RuntimeException('fail'));

        $handler = new UpsertWorkerHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }
}
