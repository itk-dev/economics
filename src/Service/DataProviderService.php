<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Exception\EconomicsException;
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

                $service = new JiraApiService(
                    $client,
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

                $service = new LeantimeApiService(
                    $client,
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
