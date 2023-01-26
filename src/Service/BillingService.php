<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Repository\ClientRepository;
use App\Repository\InvoiceEntryRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use App\Service\ProjectTracker\ApiServiceInterface;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use phpDocumentor\Reflection\Types\Collection;

class BillingService
{
    public function __construct(
        private readonly ApiServiceInterface $apiService,
        private readonly ProjectRepository $projectRepository,
        private readonly ClientRepository $clientRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceEntryRepository $invoiceEntryRepository,
        private readonly VersionRepository $versionRepository,
        private readonly WorklogRepository $worklogRepository,
        private readonly EntityManagerInterface $entityManager,
    ){
    }

    /**
     * @throws \Exception
     */
    public function syncWorklogsForProject(string $projectId, callable $progressCallback = null): void {
        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            throw new Exception("Project not found");
        }

        $worklogData = $this->apiService->getWorklogDataForProject($project->getProjectTrackerId());
        $worklogsAdded = 0;

        foreach ($worklogData as $worklogDatum) {
            $worklog = $this->worklogRepository->findOneBy(['worklogId' => $worklogDatum->projectTrackerId]);

            if (!$worklog) {
                $worklog = new Worklog();
                $worklog->setCreatedBy("sync");
                $worklog->setCreatedAt(new \DateTime());

                $this->entityManager->persist($worklog);
            }

            $worklog->setUpdatedBy("sync");
            $worklog->setUpdatedAt(new \DateTime());

            $worklog->setWorklogId($worklogDatum->projectTrackerId);
            $worklog->setDescription($worklogDatum->comment);
            $worklog->setWorker($worklogDatum->worker);
            $worklog->setStarted($worklogDatum->started);
            $worklog->setEpicKey($worklogDatum->epicKey);
            $worklog->setEpicName($worklogDatum->epicName);
            $worklog->setIssueName($worklogDatum->issueName);
            $worklog->setProjectTrackerIssueId($worklogDatum->projectTrackerIssueId);
            $worklog->setProjectTrackerIssueKey($worklogDatum->projectTrackerIssueKey);
            $worklog->setTimeSpentSeconds($worklogDatum->timeSpentSeconds);

            foreach ($worklogDatum->versions as $versionData) {
                $version = $this->versionRepository->findOneBy(['projectTrackerId' => $versionData->projectTrackerId]);

                if ($version !== null) {
                    $worklog->addVersion($version);
                }
            }

            $project->addWorklog($worklog);

            if ($progressCallback !== null) {
                $progressCallback($worklogsAdded, count($worklogData));

                $worklogsAdded++;
            }
        }

        $this->entityManager->flush();
    }

    public function updateInvoiceEntryTotalPrice(InvoiceEntry $invoiceEntry): void
    {
        $invoiceEntry->setTotalPrice(($invoiceEntry->getPrice() ?? 0) * ($invoiceEntry->getAmount()));

        $this->invoiceEntryRepository->save($invoiceEntry, true);

        $this->updateInvoiceTotalPrice($invoiceEntry->getInvoice());
    }

    public function updateInvoiceTotalPrice(Invoice $invoice): void
    {
        $totalPrice = 0;

        foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
            $totalPrice += ($invoiceEntry->getTotalPrice() ?? 0);
        }

        $invoice->setTotalPrice($totalPrice);

        $this->invoiceRepository->save($invoice, true);
    }

    public function syncProjects(callable $progressCallback): void
    {
        // Get all projects from ApiService.
        $allProjectData = $this->apiService->getAllProjectData();

        foreach ($allProjectData as $index => $projectDatum) {
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

            foreach ($projectDatum->versions as $versionData) {
                $version = $this->versionRepository->findOneBy(['projectTrackerId' => $versionData->projectTrackerId]);

                if (!$version) {
                    $version = new Version();
                    $this->entityManager->persist($version);
                }

                $version->setName($versionData->name);
                $version->setProjectTrackerId($versionData->projectTrackerId);
                $version->setProject($project);
            }

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

            $progressCallback($index, count($allProjectData));
        }

        $this->entityManager->flush();
    }
}
