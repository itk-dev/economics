<?php

namespace App\Tests\Integration\Service;

use App\Service\LeantimeApiService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Leantime is called from the message worker, and Symfony bounds neither the idle gap between chunks
 * nor the total duration of a request by default. `messenger:consume` only checks `--time-limit`
 * between messages, so an unbounded request holds the worker for as long as the other end keeps the
 * socket open.
 *
 * What is pinned here is that the service is wired to a client of its own carrying those bounds. The
 * numbers live in config/services.yaml and are read back with `bin/console debug:container`.
 */
class LeantimeApiClientTest extends KernelTestCase
{
    public function testLeantimeApiServiceDoesNotUseTheSharedClient(): void
    {
        self::bootKernel();

        $this->assertNotSame(
            self::getContainer()->get('http_client'),
            $this->leantimeHttpClient(),
            'Drop the explicit $httpClient argument and the service autowires the unbounded shared client.',
        );
    }

    public function testLeantimeClientIsBounded(): void
    {
        $options = $this->defaultOptions($this->leantimeHttpClient());

        $this->assertSame(5.0, (float) $options['timeout'], 'Idle gap between chunks.');
        $this->assertSame(30.0, (float) $options['max_duration'], 'Whole request, one page.');
    }

    private function leantimeHttpClient(): HttpClientInterface
    {
        self::bootKernel();

        $service = self::getContainer()->get(LeantimeApiService::class);
        $client = (new \ReflectionProperty($service, 'httpClient'))->getValue($service);

        $this->assertInstanceOf(HttpClientInterface::class, $client);

        return $client;
    }

    /**
     * The options the client was built with. Symfony's clients keep them in a `defaultOptions`
     * property and its decorators hold the one they wrap in `client`, so walking the stack finds
     * whichever layer carries them — the profiler wraps this one in test.
     *
     * @return array<string, mixed>
     */
    private function defaultOptions(HttpClientInterface $client): array
    {
        while (!property_exists($client, 'defaultOptions')) {
            $this->assertTrue(property_exists($client, 'client'), 'No layer of the client carries default options.');

            $inner = (new \ReflectionProperty($client, 'client'))->getValue($client);
            $this->assertInstanceOf(HttpClientInterface::class, $inner);

            $client = $inner;
        }

        return (new \ReflectionProperty($client, 'defaultOptions'))->getValue($client);
    }
}
