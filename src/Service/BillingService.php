<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ClientTypeEnum;
use App\Entity\Project;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Service\ProjectTracker\ApiServiceInterface;
use Doctrine\Common\Collections\ArrayCollection;
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
        $trackerProjects = $this->apiService->getAllProjects();

        foreach ($trackerProjects as $trackerProject) {
            $project = $this->projectRepository->findOneBy(['projectTrackerId' => $trackerProject->id]);

            if (!$project) {
                $project = new Project();
                $project->setCreatedAt(new \DateTime());
                $project->setCreatedBy("sync");
                $this->entityManager->persist($project);
            }

            $project->setName($trackerProject->name);
            $project->setProjectTrackerId($trackerProject->id);
            $project->setProjectTrackerKey($trackerProject->key);
            $project->setProjectTrackerProjectUrl($trackerProject->self);
            $project->setUpdatedBy('sync');
            $project->setUpdatedAt(new \DateTime());

            $accountIds = $this->apiService->getAccountIdsByProject($trackerProject->id);

            foreach($accountIds as $accountId) {
                $account = $this->apiService->getAccount($accountId);

                $client = $this->clientRepository->findOneBy(['projectTrackerId' => $accountId]);

                if (!$client) {
                    $client = new Client();
                    $client->setProjectTrackerId($accountId);
                    $this->entityManager->persist($client);
                }

                $client->setName($account->name);
                $client->setContact($account->contact->name ?? null);
                $client->setAccount($account->customer->key ?? null);

                if (!$client->getProjects()->contains($client)) {
                    $client->addProject($project);
                }

                switch ($account->category->name ?? null) {
                    case 'INTERN':
                        $client->setType(ClientTypeEnum::INTERNAL);
                        $client->setPsp($account->key);
                        break;
                    case 'EKSTERN':
                        $client->setType(ClientTypeEnum::EXTERNAL);
                        $client->setEan($account->key);
                        break;
                }

                $rateTable = $this->apiService->getRateTableByAccount($accountId);

                foreach ($rateTable->rates as $rate) {
                    if ('DEFAULT_RATE' === ($rate->link->type ?? '')) {
                        $client->setStandardPrice($rate->amount ?? null);
                        break;
                    }
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            $progressCallback();
        }

        $this->entityManager->flush();
    }
}
