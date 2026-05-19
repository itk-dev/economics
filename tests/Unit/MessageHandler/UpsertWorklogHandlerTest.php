<?php

namespace App\Tests\Unit\MessageHandler;

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

    public function testInvokeOnExceptionThrowsUnrecoverable(): void
    {
        $message = new UpsertWorklogMessage($this->createWorklogData());

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertWorklog')->willThrowException(new \RuntimeException('fail'));

        $handler = new UpsertWorklogHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }
}
