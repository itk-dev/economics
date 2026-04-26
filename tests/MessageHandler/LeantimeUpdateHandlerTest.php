<?php

namespace App\Tests\MessageHandler;

use App\Message\LeantimeUpdateMessage;
use App\MessageHandler\LeantimeUpdateHandler;
use App\Service\LeantimeApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class LeantimeUpdateHandlerTest extends TestCase
{
    public function testInvokeCallsUpdateAsJob(): void
    {
        $modifiedAfter = new \DateTime('2024-01-01');
        $message = new LeantimeUpdateMessage(
            'App\Entity\Project', 0, 100, 1, false, $modifiedAfter, ['PT-1'], true,
        );

        $service = $this->createMock(LeantimeApiService::class);
        $service->expects($this->once())
            ->method('updateAsJob')
            ->with('App\Entity\Project', 0, 100, 1, ['PT-1'], false, $modifiedAfter, true);

        $handler = new LeantimeUpdateHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnExceptionThrowsUnrecoverable(): void
    {
        $message = new LeantimeUpdateMessage('App\Entity\Project', 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('updateAsJob')->willThrowException(new \RuntimeException('fail'));

        $handler = new LeantimeUpdateHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }
}
