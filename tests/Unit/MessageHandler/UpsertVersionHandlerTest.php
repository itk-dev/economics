<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotFoundException;
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

    public function testInvokeOnRowLevelFailureThrowsUnrecoverable(): void
    {
        $data = new DataProviderVersionData(1, 'v1.0', 'VER-1', 'PT-1', new \DateTime(), new \DateTime());
        $message = new UpsertVersionMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertVersion')->willThrowException(new NotFoundException('fail'));

        $handler = new UpsertVersionHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnInfrastructureFailurePropagates(): void
    {
        $data = new DataProviderVersionData(1, 'v1.0', 'VER-1', 'PT-1', new \DateTime(), new \DateTime());
        $message = new UpsertVersionMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertVersion')->willThrowException(new \RuntimeException('the database went away'));

        $handler = new UpsertVersionHandler($this->createMock(LoggerInterface::class), $service);

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
