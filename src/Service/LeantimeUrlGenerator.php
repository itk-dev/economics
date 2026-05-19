<?php

namespace App\Service;

use App\Entity\Project;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LeantimeUrlGenerator
{
    public function __construct(
        #[Autowire(env: 'LEANTIME_PROJECT_TRACKER_URL')] private readonly string $baseUrl,
    ) {
    }

    public function forProject(?Project $project): ?string
    {
        return $this->forProjectTrackerId($project?->getProjectTrackerId());
    }

    public function forProjectTrackerId(?string $projectTrackerId): ?string
    {
        if (empty($this->baseUrl) || empty($projectTrackerId)) {
            return null;
        }

        return rtrim($this->baseUrl, '/').'/projects/changeCurrentProject/'.$projectTrackerId;
    }
}
