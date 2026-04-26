<?php

namespace App\Tests\MessageHandler;

use App\Enum\IssueStatusEnum;
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

    public function testInvokeOnExceptionThrowsUnrecoverable(): void
    {
        $message = new UpsertIssueMessage($this->createIssueData());

        $service = $this->createMock(DataProviderService::class);
        $service->method('upsertIssue')->willThrowException(new \RuntimeException('fail'));

        $handler = new UpsertIssueHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }
}
