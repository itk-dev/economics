<?php

namespace App\Service;

use App\Entity\DataProvider;
use App\Exception\UnsupportedDataProviderException;
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
    public function getService(DataProvider $projectTracker): DataProviderInterface
    {
        if (!in_array($projectTracker->getClass(), self::IMPLEMENTATIONS)) {
            throw new UnsupportedDataProviderException();
        }

        $service = null;

        switch ($projectTracker->getClass()) {
            case JiraApiService::class:
                $client = $this->httpClient->withOptions([
                    'base_uri' => $projectTracker->getUrl(),
                    'auth_basic' => $projectTracker->getSecret(),
                ]);

                $service = new JiraApiService(
                    $client,
                    $this->customFieldMappings,
                    $this->defaultBoard,
                    $projectTracker->getUrl(),
                    $this->weekGoalLow,
                    $this->weekGoalHigh,
                    $this->sprintNameRegex,
                );
                break;
            case LeantimeApiService::class:
                $client = $this->httpClient->withOptions([
                    'base_uri' => $projectTracker->getUrl(),
                    'headers' => [
                        // TODO: Introduce api key field to ProjectTracker
                        'x-api-key' => $projectTracker->getSecret(),
                    ],
                ]);

                $service = new LeantimeApiService(
                    $client,
                    $projectTracker->getUrl(),
                    $this->weekGoalLow,
                    $this->weekGoalHigh,
                    $this->sprintNameRegex,
                );
                break;
        }

        return $service;
    }

    public function createProjectTracker(string $name, string $class, string $url, string $basicAuth): void
    {
        $projectTracker = new DataProvider();
        $projectTracker->setName($name);
        $projectTracker->setUrl($url);
        $projectTracker->setSecret($basicAuth);
        $projectTracker->setClass($class);

        $this->entityManager->persist($projectTracker);
        $this->entityManager->flush();
    }


}
