<?php

namespace App\Tests\Integration\Repository;

use App\Entity\CybersecurityAgreement;
use App\Repository\CybersecurityAgreementRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CybersecurityAgreementRepositoryTest extends KernelTestCase
{
    private CybersecurityAgreementRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(CybersecurityAgreementRepository::class);
    }

    public function testFindAllIndexed(): void
    {
        $result = $this->repository->findAllIndexed();

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);

        foreach ($result as $key => $entity) {
            $this->assertInstanceOf(CybersecurityAgreement::class, $entity);
            $this->assertEquals($entity->getId(), $key);
        }
    }
}
