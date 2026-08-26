<?php

namespace App\Service;

use App\Entity\Project;

class LeantimeUrlGenerator
{
    public function baseUrlForProject(?Project $project): ?string
    {
        return $this->baseUrl($project?->getDataProvider()?->getUrl());
    }

    public function baseUrl(?string $url): ?string
    {
        return empty($url) ? null : rtrim($url, '/');
    }
}
