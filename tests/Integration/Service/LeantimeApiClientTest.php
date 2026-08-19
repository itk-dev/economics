<?php

namespace App\Tests\Integration\Service;

use App\Service\LeantimeApiService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ThrottlingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Leantime rate-limits its data API and a full sync makes hundreds of paged POSTs, so how the client
 * is wired decides whether a 429 costs one page or the whole run.
 *
 * The numbers themselves live in config and are read back with `bin/console debug:container` — what
 * is pinned here is the shape, because the shape is what the review turned on.
 */
class LeantimeApiClientTest extends KernelTestCase
{
    public function testLeantimeClientThrottles(): void
    {
        $this->assertInstanceOf(
            ThrottlingHttpClient::class,
            $this->leantimeHttpClient(),
            'Spending the rate limit up front is what keeps a 429 from happening at all.',
        );
    }

    /**
     * A retry here would be a second retry loop underneath the transport's, invisible to it: the
     * queue would see one slow message rather than a rate-limited one, and the backoff the async
     * transport is configured with would never get to apply.
     */
    public function testLeantimeClientDoesNotRetryUnderneathTheQueue(): void
    {
        foreach ($this->decoratorChain($this->leantimeHttpClient()) as $layer) {
            $this->assertNotInstanceOf(
                RetryableHttpClient::class,
                $layer,
                'Retrying belongs to the async transport, which retries the message, not the request.',
            );
        }
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
     * The client and everything it decorates. Symfony's decorators all hold the one they wrap in a
     * `client` property, so following it walks the whole stack.
     *
     * @return list<HttpClientInterface>
     */
    private function decoratorChain(HttpClientInterface $client): array
    {
        $chain = [$client];

        while (property_exists($client, 'client')) {
            $client = (new \ReflectionProperty($client, 'client'))->getValue($client);

            if (!$client instanceof HttpClientInterface) {
                break;
            }

            $chain[] = $client;
        }

        return $chain;
    }
}
