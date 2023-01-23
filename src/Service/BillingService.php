<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Project;
use App\Enum\ClientTypeEnum;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Service\ProjectTracker\ApiServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

class BillingService
{
    public function __construct(
        private readonly ApiServiceInterface $apiService,
        private readonly ProjectRepository $projectRepository,
        private readonly ClientRepository $clientRepository,
        private readonly EntityManagerInterface $entityManager,
    ){
    }

    public function syncProjects($progressCallback): void
    {
        // Get all projects from ApiService.
        $allProjectData = $this->apiService->getAllProjectData();

        foreach ($allProjectData as $projectDatum) {
            $project = $this->projectRepository->findOneBy(['projectTrackerId' => $projectDatum->projectTrackerId]);

            if (!$project) {
                $project = new Project();
                $project->setCreatedAt(new \DateTime());
                $project->setCreatedBy("sync");
                $this->entityManager->persist($project);
            }

            $project->setName($projectDatum->name);
            $project->setProjectTrackerId($projectDatum->projectTrackerId);
            $project->setProjectTrackerKey($projectDatum->projectTrackerKey);
            $project->setProjectTrackerProjectUrl($projectDatum->projectTrackerProjectUrl);
            $project->setUpdatedBy('sync');
            $project->setUpdatedAt(new \DateTime());

            $projectClientData = $this->apiService->getClientDataForProject($projectDatum->projectTrackerId);

            foreach($projectClientData as $clientData) {
                $client = $this->clientRepository->findOneBy(['projectTrackerId' => $clientData->projectTrackerId]);

                if (!$client) {
                    $client = new Client();
                    $client->setProjectTrackerId($clientData->projectTrackerId);
                    $this->entityManager->persist($client);
                }

                $client->setName($clientData->name);
                $client->setContact($clientData->contact);
                $client->setAccount($clientData->account);
                $client->setType($clientData->type);
                $client->setPsp($clientData->psp);
                $client->setEan($clientData->ean);
                $client->setStandardPrice($clientData->standardPrice);

                if (!$client->getProjects()->contains($client)) {
                    $client->addProject($project);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            $progressCallback();
        }

        $this->entityManager->flush();
    }
}
