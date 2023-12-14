<?php

namespace App\Service;

use App\Entity\ProjectTracker;
use Doctrine\ORM\EntityManagerInterface;

class ProjectTrackerService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function createProjectTracker(string $name, string $url, string $basicAuth): void
    {
        $projectTracker = new ProjectTracker();
        $projectTracker->setName($name);
        $projectTracker->setUrl($url);
        $projectTracker->setBasicAuth($basicAuth);

        $this->entityManager->persist($projectTracker);
        $this->entityManager->flush();
    }
}
