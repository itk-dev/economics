<?php

namespace App\Service;

use App\Entity\Client;

class ClientHelper
{
    public function __construct(
        private readonly array $options,
    ) {
    }

    /**
     * Get standard price from client with fallback to global value.
     */
    public function getStandardPrice(?Client $client = null)
    {
        $standardPrice = (float) $this->options['standard_price'];

        return $client?->getStandardPrice() ?? $standardPrice;
    }
}
