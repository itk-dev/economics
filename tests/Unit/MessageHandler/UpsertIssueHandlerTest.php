<?php

namespace App\Tests\Unit\MessageHandler;

use App\Enum\IssueStatusEnum;
use App\Exception\NotFoundException;
use App\Message\UpsertIssueMessage;
use App\MessageHandler\UpsertIssueHandler;
use App\Model\DataProvider\DataProviderIssueData;
use App\Service\DataProviderService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class UpsertIssueHandlerTest extends TestCase
{
    private function createIssueData(): DataProviderIssueData
    {
        return new DataProviderIssueData(
            'ISS-1', 1, 'PT-1', 'Test Issue', [], 0.0, 0.0, null,
            IssueStatusEnum::NEW, null, null, new \DateTime(), 'http://test', new \DateTime(), null,
        );
    }

    public function testInvokeCallsUpsertIssue(): void
    {
        $data = $this->createIssueData();
        $message = new UpsertIssueMessage($data);

        $service = $this->createMock(DataProviderService::class);
        $service->expects($this->once())->method('upsertIssue')->with($data);

        $handler = new UpsertIssueHandler($this->createMock(LoggerInterface::class), $service);
        $handler($message);
    }

    public function testInvokeOnRowLevelFailureThrowsUnrecoverable(): void
    {
        $message = new UpsertIssueMessage($this->createIssueData());

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertIssue')->willThrowException(new NotFoundException('fail'));

        $handler = new UpsertIssueHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnInfrastructureFailurePropagates(): void
    {
        $message = new UpsertIssueMessage($this->createIssueData());

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertIssue')->willThrowException(new \RuntimeException('the database went away'));

        $handler = new UpsertIssueHandler($this->createMock(LoggerInterface::class), $service);

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
