<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Exception\UnsupportedDataProviderException;
use App\Interface\DataProviderServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {}

    /**
     * @throws UnsupportedDataProviderException
     */
    public function getService(DataProvider $dataProvider): DataProviderServiceInterface
    {
        if (!in_array($dataProvider->getClass(), self::IMPLEMENTATIONS)) {
            throw new UnsupportedDataProviderException();
        }

        $service = null;

        switch ($dataProvider->getClass()) {
            case JiraApiService::class:
                $client = $this->httpClient->withOptions([
                    'base_uri' => $dataProvider->getUrl(),
                    'auth_basic' => $dataProvider->getSecret(),
                ]);

                $service = new JiraApiService(
                    $client,
                    $this->customFieldMappings,
                    $this->defaultBoard,
                    $dataProvider->getUrl(),
                    $this->weekGoalLow,
                    $this->weekGoalHigh,
                    $this->sprintNameRegex,
                );
                break;
            case LeantimeApiService::class:
                $client = $this->httpClient->withOptions([
                    'base_uri' => $dataProvider->getUrl(),
                    'headers' => [
                        'x-api-key' => $dataProvider->getSecret(),
                    ],
                ]);

                $service = new LeantimeApiService(
                    $client,
                    $dataProvider->getUrl(),
                    $this->weekGoalLow,
                    $this->weekGoalHigh,
                    $this->sprintNameRegex,
                );
                break;
        }

        return $service;
    }

    public function createDataProvider(string $name, string $class, string $url, string $secret): void
    {
        $dataProvider = new DataProvider();
        $dataProvider->setName($name);
        $dataProvider->setUrl($url);
        $dataProvider->setSecret($secret);
        $dataProvider->setClass($class);

        $this->entityManager->persist($dataProvider);
        $this->entityManager->flush();
    }
}
