<?php

namespace App\Tests\Integration\Service;

use App\Entity\DataProvider;
use App\Entity\Project;
use App\Message\LeantimeUpdateMessage;
use App\Message\UpsertProjectMessage;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Service\LeantimeApiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * Leantime rate-limits its data API, and the sync makes hundreds of paged POSTs per run.
 *
 * A 429 that reaches updateAsJob() ends the pagination chain, because the next page is only queued
 * after the fetch succeeds. RetryableHttpClient used to guard this and was lost when the old Jira
 * stack was deleted; these tests pin it back down.
 */
class LeantimeApiServiceRetryTest extends KernelTestCase
{
    private const LIMIT = 100;

    /** @var list<object> */
    private array $dispatched = [];

    public function testLeantimeClientIsRetryable(): void
    {
        self::bootKernel();

        $service = self::getContainer()->get(LeantimeApiService::class);

        $client = (new \ReflectionProperty($service, 'httpClient'))->getValue($service);

        $this->assertInstanceOf(
            RetryableHttpClient::class,
            $client,
            'LeantimeApiService must get a retrying client, or one 429 ends the pagination chain.',
        );
    }

    /**
     * The regression itself: a rate-limited page must be retried, not dropped.
     */
    public function testRateLimitedPageIsRetriedAndStillPaginates(): void
    {
        $service = $this->createService([
            new MockResponse('', ['http_code' => 429]),
            new MockResponse($this->fullPageJson(), ['http_code' => 200]),
        ]);

        $service->updateAsJob(Project::class, 0, self::LIMIT, 1);

        $this->assertCount(self::LIMIT, $this->dispatchedOfType(UpsertProjectMessage::class), 'Every row of the retried page should still be upserted.');
        $this->assertCount(1, $this->dispatchedOfType(LeantimeUpdateMessage::class), 'The chain must continue to the next page.');
    }

    public function testServiceUnavailableIsRetried(): void
    {
        $service = $this->createService([
            new MockResponse('', ['http_code' => 503]),
            new MockResponse($this->fullPageJson(), ['http_code' => 200]),
        ]);

        $service->updateAsJob(Project::class, 0, self::LIMIT, 1);

        $this->assertCount(1, $this->dispatchedOfType(LeantimeUpdateMessage::class));
    }

    /**
     * The Leantime data API is POST-only, including for reads, and every call this service makes is
     * a read. GenericRetryStrategy's own defaults express 500/504/507/510 and transport errors as
     * [code => idempotent methods], which excludes POST — so those cases would silently stop being
     * retried if the configured list were ever "simplified" back to the defaults. A flat list of
     * codes is what makes them apply to POST.
     */
    public function testRetryStrategyCoversPostForTransportErrorsAndServerErrors(): void
    {
        self::bootKernel();

        $strategy = self::getContainer()->get(RetryStrategyInterface::class);
        $this->assertInstanceOf(RetryStrategyInterface::class, $strategy);

        $statusCodes = (array) (new \ReflectionProperty($strategy, 'statusCodes'))->getValue($strategy);

        // 0 is the transport-error slot: connection reset, DNS failure, timeout.
        foreach ([0, 429, 500, 502, 503, 504] as $code) {
            $this->assertContains(
                $code,
                $statusCodes,
                sprintf('%d must be retried for any method; a [code => methods] entry would skip POST.', $code),
            );
        }
    }

    /**
     * Retrying must not turn a genuinely unavailable API into a silent success: once the retries are
     * spent the error still has to surface, so the caller can fail the page visibly.
     */
    public function testPersistentRateLimitStillFails(): void
    {
        $service = $this->createService(array_fill(0, 10, new MockResponse('', ['http_code' => 429])));

        $this->expectException(HttpExceptionInterface::class);

        $service->updateAsJob(Project::class, 0, self::LIMIT, 1);
    }

    /**
     * A full page of project rows, so the chain queues a next page. Only the transport behaviour is
     * under test, so the payload is fixed and valid.
     */
    private function fullPageJson(): string
    {
        $results = array_map(static fn (int $id) => [
            'id' => $id,
            'name' => 'Project '.$id,
            'modified' => '2026-07-30 12:00:00',
        ], range(1, self::LIMIT));

        return json_encode([
            'results' => $results,
            'resultsCount' => count($results),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * The service under test, wrapping the given mock responses in the retry strategy the container
     * actually configures — the point is to exercise the real strategy, not a copy of it.
     *
     * @param list<MockResponse> $responses
     */
    private function createService(array $responses): LeantimeApiService
    {
        self::bootKernel();
        $container = self::getContainer();

        $strategy = $container->get(RetryStrategyInterface::class);
        $this->assertInstanceOf(RetryStrategyInterface::class, $strategy);

        $httpClient = new RetryableHttpClient(new MockHttpClient($responses), $strategy, 3);

        $this->dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(
            function (object $message, array $stamps = []): Envelope {
                $this->dispatched[] = $message;

                return new Envelope($message, $stamps);
            }
        );

        $dataProvider = new DataProvider();
        $dataProvider->setName('Retry test provider');
        $dataProvider->setEnabled(true);
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('http://leantime.example.com');
        $dataProvider->setSecret('Not so secret');

        $dataProviderRepository = $this->createMock(DataProviderRepository::class);
        $dataProviderRepository->method('find')->willReturn($dataProvider);

        return new LeantimeApiService(
            $httpClient,
            $messageBus,
            $dataProviderRepository,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(ProjectRepository::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    /**
     * @return list<object>
     */
    private function dispatchedOfType(string $class): array
    {
        return array_values(array_filter($this->dispatched, static fn (object $m) => $m instanceof $class));
    }
}
