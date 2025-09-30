<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Interface\DataProviderServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
