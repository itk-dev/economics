<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotFoundException;
use App\Message\LeantimeDeleteMessage;
use App\MessageHandler\LeantimeDeleteHandler;
use App\Service\LeantimeApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class LeantimeDeleteHandlerTest extends TestCase
{
    /**
     * The endpoint answers 400 to a missing `type` or the retired `deleted` parameter, and no amount
     * of retrying rewrites the request.
     */
    public function testInvokeOnPermanentClientErrorThrowsUnrecoverable(): void
    {
        $message = new LeantimeDeleteMessage(LeantimeApiService::TIMESHEETS, 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('deleteAsJob')->willThrowException($this->clientException(400));

        $handler = new LeantimeDeleteHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    public function testInvokeOnRateLimitPropagates(): void
    {
        $message = new LeantimeDeleteMessage(LeantimeApiService::TIMESHEETS, 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('deleteAsJob')->willThrowException($this->clientException(429));

        $handler = new LeantimeDeleteHandler($this->createMock(LoggerInterface::class), $service);

        try {
            $handler($message);
            $this->fail('Expected the rate limit to propagate.');
        } catch (ClientException $e) {
            $this->assertSame(429, $e->getResponse()->getStatusCode());
        }
    }

    private function clientException(int $statusCode): ClientException
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => $statusCode]));
        $response = $client->request('POST', 'https://leantime.example.com/APIData/API/deleted');

        // Reading the code settles the mock, so ClientException finds the info it formats from.
        $response->getStatusCode();

        return new ClientException($response);
    }

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
