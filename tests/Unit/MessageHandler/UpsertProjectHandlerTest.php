<?php

namespace App\Tests\Unit\MessageHandler;

use App\Message\UpsertProjectMessage;
use App\MessageHandler\UpsertProjectHandler;
use App\Model\DataProvider\DataProviderProjectData;
use App\Service\DataProviderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class UpsertProjectHandlerTest extends TestCase
{
    public function testInvokeCallsUpsertProject(): void
    {
        $data = new DataProviderProjectData(1, 'Test', 'PT-1', 'http://test', new \DateTime(), new \DateTime());
        $message = new UpsertProjectMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->expects($this->once())->method('upsertProject')->with($data);

        $handler = new UpsertProjectHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnExceptionThrowsUnrecoverable(): void
    {
        $data = new DataProviderProjectData(1, 'Test', 'PT-1', 'http://test', new \DateTime(), new \DateTime());
        $message = new UpsertProjectMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertProject')->willThrowException(new \RuntimeException('fail'));

        $handler = new UpsertProjectHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }
}
