<?php

namespace App\Tests\Integration\Repository;

use App\Repository\CybersecurityAgreementRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CybersecurityAgreementRepositoryTest extends KernelTestCase
{
    private CybersecurityAgreementRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CybersecurityAgreementRepository::class);
        \assert($repository instanceof CybersecurityAgreementRepository);
        $this->repository = $repository;
    }

    public function testFindAllIndexed(): void
    {
        $result = $this->repository->findAllIndexed();

        $this->assertNotEmpty($result);

        foreach ($result as $key => $entity) {
            $this->assertEquals($entity->getId(), $key);
        }
    }
}
