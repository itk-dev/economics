<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Interface\DataProviderServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;

class DataProviderService
{
    public const IMPLEMENTATIONS = [
        JiraApiService::class,
        LeantimeApiService::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        protected readonly HttpClientInterface $httpClient,
        protected readonly array $customFieldMappings,
        protected readonly string $defaultBoard,
        protected readonly float $weekGoalLow,
        protected readonly float $weekGoalHigh,
        protected readonly string $sprintNameRegex,
        protected readonly int $httpClientRetryDelayMs = 1000,
        protected readonly int $httpClientMaxRetries = 3,
    ) {
    }

    /**
     * @throws UnsupportedDataProviderException
     * @throws EconomicsException
     */
    public function getService(DataProvider $dataProvider): DataProviderServiceInterface
    {
        if (!in_array($dataProvider->getClass(), self::IMPLEMENTATIONS)) {
            throw new UnsupportedDataProviderException();
        }

        $url = $dataProvider->getUrl();
        if (null == $url) {
            throw new EconomicsException('Data provider url is null');
        }

        switch ($dataProvider->getClass()) {
            case JiraApiService::class:
                $client = $this->httpClient->withOptions([
                    'base_uri' => $url,
                    'auth_basic' => $dataProvider->getSecret(),
                ]);

                $retryableHttpClient = new RetryableHttpClient($client, new GenericRetryStrategy([429], $this->httpClientRetryDelayMs, 1.0), $this->httpClientMaxRetries);

                $service = new JiraApiService(
                    $retryableHttpClient,
                    $this->customFieldMappings,
                    $this->defaultBoard,
                    $url,
                    $this->weekGoalLow,
                    $this->weekGoalHigh,
                    $this->sprintNameRegex,
                );
                break;
            case LeantimeApiService::class:
                $client = $this->httpClient->withOptions([
                    'base_uri' => $url,
                    'headers' => [
                        'x-api-key' => $dataProvider->getSecret(),
                    ],
                ]);

                $retryableHttpClient = new RetryableHttpClient($client, new GenericRetryStrategy([429], $this->httpClientRetryDelayMs, 1.0), $this->httpClientMaxRetries);

                $service = new LeantimeApiService(
                    $retryableHttpClient,
                    $url,
                    $this->weekGoalLow,
                    $this->weekGoalHigh,
                    $this->sprintNameRegex,
                );
                break;
            default:
                throw new UnsupportedDataProviderException();
        }

        return $service;
    }

    public function createDataProvider(string $name, string $class, string $url, string $secret, bool $enableClientSync = false, bool $enableAccountSync = false): DataProvider
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName($name);
        $dataProvider->setUrl($url);
        $dataProvider->setSecret($secret);
        $dataProvider->setClass($class);
        $dataProvider->setEnabled(true);
        $dataProvider->setEnableClientSync($enableClientSync);
        $dataProvider->setEnableAccountSync($enableAccountSync);

        $this->entityManager->persist($dataProvider);
        $this->entityManager->flush();

        return $dataProvider;
    }
}
