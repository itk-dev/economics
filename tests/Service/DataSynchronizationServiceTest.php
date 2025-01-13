<?php

namespace App\Tests\Service;

use App\Repository\EpicRepository;
use App\Service\DataSynchronizationService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DataSynchronizationServiceTest extends KernelTestCase
{
    public function testGetIssuesNotIncludedInProjectBilling(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $epicRepository = $container->get(EpicRepository::class);

        $this->assertCount(1, $epicRepository->findAll());

        $dataSynchronizationService = $container->get(DataSynchronizationService::class);

        $dataSynchronizationService->migrateEpics();

        $this->assertCount(3, $epicRepository->findAll());
    }
}
