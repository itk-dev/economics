<?php

namespace App\Tests\MessageHandler;

use App\Message\UpsertVersionMessage;
use App\MessageHandler\UpsertVersionHandler;
use App\Model\DataProvider\DataProviderVersionData;
use App\Service\DataProviderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class UpsertVersionHandlerTest extends TestCase
{
    public function testInvokeCallsUpsertVersion(): void
    {
        $data = new DataProviderVersionData(1, 'v1.0', 'VER-1', 'PT-1', new \DateTime(), new \DateTime());
        $message = new UpsertVersionMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->expects($this->once())->method('upsertVersion')->with($data);

        $handler = new UpsertVersionHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnExceptionThrowsUnrecoverable(): void
    {
        $data = new DataProviderVersionData(1, 'v1.0', 'VER-1', 'PT-1', new \DateTime(), new \DateTime());
        $message = new UpsertVersionMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertVersion')->willThrowException(new \RuntimeException('fail'));

        $handler = new UpsertVersionHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }
}
