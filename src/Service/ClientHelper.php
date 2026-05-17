<?php

namespace App\Service;

use App\Entity\Client;

class ClientHelper
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
    ) {
    }

    /**
     * Get standard price from client with fallback to global value.
     */
    public function getStandardPrice(?Client $client = null): float
    {
        $standardPrice = (float) $this->options['standard_price'];

        return $client?->getStandardPrice() ?? $standardPrice;
    }
}
