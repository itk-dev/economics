<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotFoundException;
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

    public function testInvokeOnRowLevelFailureThrowsUnrecoverable(): void
    {
        $message = new LeantimeUpdateMessage('App\Entity\Project', 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('updateAsJob')->willThrowException(new NotFoundException('fail'));

        $handler = new LeantimeUpdateHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnInfrastructureFailurePropagates(): void
    {
        $message = new LeantimeUpdateMessage('App\Entity\Project', 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('updateAsJob')->willThrowException(new \RuntimeException('the database went away'));

        $handler = new LeantimeUpdateHandler($this->createMock(LoggerInterface::class), $service);

        // Dropping this page would also drop every page after it, since each queues the next.
        try {
            $handler($message);
            $this->fail('Expected the failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            $this->assertSame('the database went away', $e->getMessage());
        }
    }
}
