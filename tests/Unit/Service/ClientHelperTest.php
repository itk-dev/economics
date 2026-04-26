<?php

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Service\ClientHelper;
use PHPUnit\Framework\TestCase;

class ClientHelperTest extends TestCase
{
    public function testGetStandardPriceWithClientPrice(): void
    {
        $helper = new ClientHelper(['standard_price' => 500.0]);

        $client = new Client();
        $client->setName('Test');
        $client->setStandardPrice(750.0);

        $this->assertEqualsWithDelta(750.0, $helper->getStandardPrice($client), 0.001);
    }

    public function testGetStandardPriceWithClientWithoutPrice(): void
    {
        $helper = new ClientHelper(['standard_price' => 500.0]);

        $client = new Client();
        $client->setName('Test');
        // No standard price set (null)

        $this->assertEqualsWithDelta(500.0, $helper->getStandardPrice($client), 0.001);
    }

    public function testGetStandardPriceWithNullClient(): void
    {
        $helper = new ClientHelper(['standard_price' => 500.0]);

        $this->assertEqualsWithDelta(500.0, $helper->getStandardPrice(null), 0.001);
    }

    public function testGetStandardPriceWithNoArgument(): void
    {
        $helper = new ClientHelper(['standard_price' => 500.0]);

        $this->assertEqualsWithDelta(500.0, $helper->getStandardPrice(), 0.001);
    }
}
