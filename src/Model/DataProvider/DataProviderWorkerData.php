<?php

namespace App\Model\DataProvider;

class DataProviderWorkerData
{
    public function __construct(
        public int $dataProviderId,
        public string $name,
        public string $email,
    ) {
    }
}
