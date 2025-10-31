<?php

namespace App\Message;

readonly class EntityRemovedFromDataProviderMessage
{
    public function __construct(
        public string $classname,
        public int $dataProviderId,
        public string $projectTrackerId,
    ) {
    }
}
