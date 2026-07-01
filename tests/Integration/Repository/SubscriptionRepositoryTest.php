<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Subscription;
use App\Enum\SubscriptionFrequencyEnum;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SubscriptionRepositoryTest extends KernelTestCase
{
    private SubscriptionRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(SubscriptionRepository::class);
    }

    public function testFindByCustom(): void
    {
        $result = $this->repository->findByCustom('subscriber@test.com', ['param1' => 'value1']);

        $this->assertCount(2, $result);
        foreach ($result as $subscription) {
            $this->assertInstanceOf(Subscription::class, $subscription);
            $this->assertEquals('subscriber@test.com', $subscription->getEmail());
        }
    }

    public function testFindByCustomNoMatch(): void
    {
        $result = $this->repository->findByCustom('subscriber@test.com', ['nonexistent' => 'param']);

        $this->assertEmpty($result);
    }

    public function testFindOneByCustom(): void
    {
        $result = $this->repository->findOneByCustom(
            'subscriber@test.com',
            SubscriptionFrequencyEnum::FREQUENCY_MONTHLY,
            ['param1' => 'value1']
        );

        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals('subscriber@test.com', $result->getEmail());
        $this->assertEquals(SubscriptionFrequencyEnum::FREQUENCY_MONTHLY, $result->getFrequency());
    }

    public function testFindOneByCustomNoMatch(): void
    {
        $result = $this->repository->findOneByCustom(
            'nonexistent@test.com',
            SubscriptionFrequencyEnum::FREQUENCY_MONTHLY,
            ['param1' => 'value1']
        );

        $this->assertNull($result);
    }

    public function testGetFilteredData(): void
    {
        $result = $this->repository->getFilteredData('subscriber@test.com');

        $this->assertCount(2, $result);
        foreach ($result as $subscription) {
            $this->assertEquals('subscriber@test.com', $subscription->getEmail());
        }
    }

    public function testGetFilteredDataNoMatch(): void
    {
        $result = $this->repository->getFilteredData('nonexistent@test.com');

        $this->assertEmpty($result);
    }
}
