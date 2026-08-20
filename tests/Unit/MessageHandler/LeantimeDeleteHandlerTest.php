<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotFoundException;
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
        $message = new LeantimeDeleteMessage(LeantimeApiService::TICKETS, 82, 100, 1, false, $deletedAfter);

        $service = $this->createMock(LeantimeApiService::class);
        $service->expects($this->once())
            ->method('deleteAsJob')
            ->with(LeantimeApiService::TICKETS, 82, 100, 1, false, $deletedAfter);

        $handler = new LeantimeDeleteHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnRowLevelFailureThrowsUnrecoverable(): void
    {
        $message = new LeantimeDeleteMessage(LeantimeApiService::TICKETS, 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('deleteAsJob')->willThrowException(new NotFoundException('fail'));

        $handler = new LeantimeDeleteHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnInfrastructureFailurePropagates(): void
    {
        $message = new LeantimeDeleteMessage(LeantimeApiService::TICKETS, 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('deleteAsJob')->willThrowException(new \RuntimeException('the database went away'));

        $handler = new LeantimeDeleteHandler($this->createMock(LoggerInterface::class), $service);

        // Marking this unrecoverable would drop the message, so a broken run would report success.
        try {
            $handler($message);
            $this->fail('Expected the failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            $this->assertSame('the database went away', $e->getMessage());
        }
    }
}
