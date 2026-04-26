<?php

namespace App\Tests\MessageHandler;

use App\Message\LeantimeDeleteMessage;
use App\MessageHandler\LeantimeDeleteHandler;
use App\Service\LeantimeApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class LeantimeDeleteHandlerTest extends TestCase
{
    public function testInvokeCallsDeleteAsJob(): void
    {
        $deletedAfter = new \DateTime('2024-01-01');
        $message = new LeantimeDeleteMessage(1, false, $deletedAfter);

        $service = $this->createMock(LeantimeApiService::class);
        $service->expects($this->once())
            ->method('deleteAsJob')
            ->with(1, false, $deletedAfter);

        $handler = new LeantimeDeleteHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnExceptionThrowsUnrecoverable(): void
    {
        $message = new LeantimeDeleteMessage(1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('deleteAsJob')->willThrowException(new \RuntimeException('fail'));

        $handler = new LeantimeDeleteHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }
}
