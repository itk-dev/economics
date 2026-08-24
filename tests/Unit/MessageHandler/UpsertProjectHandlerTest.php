<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotFoundException;
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

    public function testInvokeOnRowLevelFailureThrowsUnrecoverable(): void
    {
        $data = new DataProviderProjectData(1, 'Test', 'PT-1', 'http://test', new \DateTime(), new \DateTime());
        $message = new UpsertProjectMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertProject')->willThrowException(new NotFoundException('fail'));

        $handler = new UpsertProjectHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnInfrastructureFailurePropagates(): void
    {
        $data = new DataProviderProjectData(1, 'Test', 'PT-1', 'http://test', new \DateTime(), new \DateTime());
        $message = new UpsertProjectMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertProject')->willThrowException(new \RuntimeException('the database went away'));

        $handler = new UpsertProjectHandler($this->createMock(LoggerInterface::class), $service);

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
