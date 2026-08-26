<?php

namespace App\Tests\Unit\MessageHandler;

use App\Exception\NotFoundException;
use App\Message\LeantimeUpdateMessage;
use App\MessageHandler\LeantimeUpdateHandler;
use App\Service\LeantimeApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class LeantimeUpdateHandlerTest extends TestCase
{
    /**
     * A 4xx that Leantime will answer the same way next time. Retrying spends the whole backoff to
     * arrive back here, so the message is dropped instead.
     */
    public function testInvokeOnPermanentClientErrorThrowsUnrecoverable(): void
    {
        $message = new LeantimeUpdateMessage('App\Entity\Project', 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('updateAsJob')->willThrowException($this->clientException(400));

        $handler = new LeantimeUpdateHandler($this->createMock(LoggerInterface::class), $service);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $handler($message);
    }

    /**
     * The regression this branch exists for: a rate-limited page must reach the transport unwrapped,
     * so the async retry strategy queues it again instead of the sync ending here.
     */
    public function testInvokeOnRateLimitPropagates(): void
    {
        $message = new LeantimeUpdateMessage('App\Entity\Project', 0, 100, 1, false, null);

        $service = $this->createMock(LeantimeApiService::class);
        $service->method('updateAsJob')->willThrowException($this->clientException(429));

        $handler = new LeantimeUpdateHandler($this->createMock(LoggerInterface::class), $service);

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
        $response = $client->request('POST', 'https://leantime.example.com/APIData/API/projects');

        // Reading the code settles the mock, so ClientException finds the info it formats from.
        $response->getStatusCode();

        return new ClientException($response);
    }

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
