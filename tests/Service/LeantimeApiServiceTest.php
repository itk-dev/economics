<?php

namespace App\Tests\Service;

use App\Entity\DataProvider;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\BillableKindsEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\IssueStatusEnum;
use App\Repository\DataProviderRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectRepository;
use App\Repository\VersionRepository;
use App\Repository\WorklogRepository;
use App\Service\LeantimeApiService;
use Doctrine\ORM\EntityManagerInterface;
use http\Client\Response;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LeantimeApiServiceTest extends KernelTestCase
{
    /*
    public function testSynchronization(): void
    {
        self::bootKernel();
        $container = self::getContainer();


    }
    */

    public function testDeleted(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $messageBus = $container->get(MessageBusInterface::class);
        $dataProviderRepository = $container->get(DataProviderRepository::class);
        $projectRepository = $container->get(ProjectRepository::class);
        $versionRepository = $container->get(VersionRepository::class);
        $issueRepository = $container->get(IssueRepository::class);
        $worklogRepository = $container->get(WorklogRepository::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $loggerMock = $this->createMock(LoggerInterface::class);

        $httpClientMock = $this->createMock(HttpClientInterface::class);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getContent')->willReturn(json_encode($this->getDeletedData()));

        $httpClientMock->method('request')->willReturn($responseMock);

        $service = new LeantimeApiService(
            $httpClientMock,
            $messageBus,
            $dataProviderRepository,
            $entityManager,
            $projectRepository,
            $loggerMock,
        );

        $dataProvider = new DataProvider();
        $dataProvider->setName('Data Provider 3 - Leantime 3');
        $dataProvider->setEnabled(true);
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('http://localhost/');
        $dataProvider->setSecret('Not so secret');
        $entityManager->persist($dataProvider);

        $countProjectsBeforeCreate = count($projectRepository->findAll());
        $countVersionsBeforeCreate = count($versionRepository->findAll());
        $countIssuesBeforeCreate = count($issueRepository->findAll());
        $countWorklogsBeforeCreate = count($worklogRepository->findAll());

        $project1 = new Project();
        $project1->setDataProvider($dataProvider);
        $project1->setProjectTrackerId(64);
        $project1->setName('Project to delete - protected');
        $project1->setProjectTrackerKey(64);
        $project1->setProjectTrackerProjectUrl('http://localhost/');
        $project1->setInclude(true);
        $project1->setProjectLeadMail('test@economics.local.itkdev.dk');
        $project1->setProjectLeadName('Test Testesen');
        $project1->setIsBillable(true);
        $entityManager->persist($project1);

        $project2 = new Project();
        $project2->setDataProvider($dataProvider);
        $project2->setProjectTrackerId(65);
        $project2->setName('Project to delete');
        $project2->setProjectTrackerKey(65);
        $project2->setProjectTrackerProjectUrl('http://localhost/');
        $project2->setInclude(true);
        $project2->setProjectLeadMail('test@economics.local.itkdev.dk');
        $project2->setProjectLeadName('Test Testesen');
        $project2->setIsBillable(true);
        $entityManager->persist($project2);

        $version1 = new Version();
        $version1->setDataProvider($dataProvider);
        $version1->setName("Version 1");
        $version1->setProject($project1);
        $version1->setProjectTrackerId(6724);
        $entityManager->persist($version1);

        $version2 = new Version();
        $version2->setDataProvider($dataProvider);
        $version2->setName("Version 2 - protected");
        $version2->setProject($project2);
        $version2->setProjectTrackerId(6725);
        $entityManager->persist($version2);

        $issue1 = new Issue();
        $issue1->setDataProvider($dataProvider);
        $issue1->setProject($project1);
        $issue1->setProjectTrackerId(6723);
        $issue1->setProjectTrackerKey(6723);
        $issue1->setName("issue 1");
        $issue1->setAccountId('Account 1');
        $issue1->setAccountKey('Account 1');
        $issue1->setEpicName('Epic 1');
        $issue1->setEpicKey('Epic 1');
        $issue1->setStatus(IssueStatusEnum::DONE);
        $issue1->setDataProvider($dataProvider);
        $issue1->addVersion($version1);
        $issue1->setResolutionDate(new \DateTime());
        $issue1->setPlanHours(1);
        $issue1->setHoursRemaining(1);
        $issue1->setWorker("admin@example.com");
        $issue1->setDueDate(new \DateTime());
        $issue1->setLinkToIssue('www.example.com');
        $entityManager->persist($issue1);

        $issue2 = new Issue();
        $issue2->setDataProvider($dataProvider);
        $issue2->setProject($project1);
        $issue2->setProjectTrackerId(6726);
        $issue2->setProjectTrackerKey(6726);
        $issue2->setName("issue 2");
        $issue2->setAccountId('Account 1');
        $issue2->setAccountKey('Account 1');
        $issue2->setEpicName('Epic 1');
        $issue2->setEpicKey('Epic 1');
        $issue2->setStatus(IssueStatusEnum::DONE);
        $issue2->setDataProvider($dataProvider);
        $issue2->addVersion($version1);
        $issue2->setResolutionDate(new \DateTime());
        $issue2->setPlanHours(1);
        $issue2->setHoursRemaining(1);
        $issue2->setWorker("admin@example.com");
        $issue2->setDueDate(new \DateTime());
        $issue2->setLinkToIssue('www.example.com');
        $entityManager->persist($issue2);

        $worklog1 = new Worklog();
        $worklog1->setProject($project1);
        $worklog1->setDataProvider($dataProvider);
        $worklog1->setProjectTrackerIssueId(6723);
        $worklog1->setWorklogId(66937);
        $worklog1->setDescription("Beskrivelse af worklog");
        $worklog1->setIsBilled(false);
        $worklog1->setWorker("admin@example.com");
        $worklog1->setTimeSpentSeconds(60 * 15);
        $worklog1->setStarted(\DateTime::createFromFormat('U', (string) strtotime("2024-01-01"), new \DateTimeZone('Europe/Copenhagen')));
        $worklog1->setIssue($issue1);
        $worklog1->setDataProvider($dataProvider);
        $worklog1->setKind(BillableKindsEnum::GENERAL_BILLABLE);
        $entityManager->persist($worklog1);

        $worklog2 = new Worklog();
        $worklog2->setProject($project1);
        $worklog2->setDataProvider($dataProvider);
        $worklog2->setProjectTrackerIssueId(6726);
        $worklog2->setWorklogId(66938);
        $worklog2->setDescription("Beskrivelse af worklog");
        $worklog2->setIsBilled(false);
        $worklog2->setWorker("admin@example.com");
        $worklog2->setTimeSpentSeconds(60 * 15);
        $worklog2->setStarted(\DateTime::createFromFormat('U', (string) strtotime("2024-01-01"), new \DateTimeZone('Europe/Copenhagen')));
        $worklog2->setIssue($issue2);
        $worklog2->setDataProvider($dataProvider);
        $worklog2->setKind(BillableKindsEnum::GENERAL_BILLABLE);
        $entityManager->persist($worklog2);

        $entityManager->flush();

        $countProjectsAfterCreate = count($projectRepository->findAll());
        $countVersionsAfterCreate = count($versionRepository->findAll());
        $countIssuesAfterCreate = count($issueRepository->findAll());
        $countWorklogsAfterCreate = count($worklogRepository->findAll());

        $this->assertEquals($countProjectsBeforeCreate + 2, $countProjectsAfterCreate);
        $this->assertEquals($countVersionsBeforeCreate + 2, $countVersionsAfterCreate);
        $this->assertEquals($countIssuesBeforeCreate + 2, $countIssuesAfterCreate);
        $this->assertEquals($countWorklogsBeforeCreate + 2, $countWorklogsAfterCreate);

        // Create Invoice and InvoiceEntry to test protection of elements that are bound to invoices.

        $invoice = new Invoice();
        $invoice->setProject($project2);
        $invoice->setName("Invoice 1");
        $invoice->setRecorded(false);
        $entityManager->persist($invoice);

        $invoiceEntry = new InvoiceEntry();
        $invoiceEntry->setInvoice($invoice);
        $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $invoiceEntry->setIndex(1);
        $invoiceEntry->addWorklog($worklog2);
        $entityManager->persist($invoiceEntry);

        $entityManager->flush();

        $id = $dataProvider->getId();

        $entityManager->clear();

        $service->deleteAsJob($id, false, new \DateTime("2025-10-06T11:36:08.000000Z"));

        $countProjectsAfterDelete = count($projectRepository->findAll());
        $countVersionsAfterDelete = count($versionRepository->findAll());
        $countIssuesAfterDelete = count($issueRepository->findAll());
        $countWorklogsAfterDelete = count($worklogRepository->findAll());

        $this->assertEquals($countWorklogsBeforeCreate + 1, $countWorklogsAfterDelete);
        $this->assertEquals($countIssuesBeforeCreate + 1, $countIssuesAfterDelete);
        // Versions can always be removed.
        $this->assertEquals($countVersionsBeforeCreate, $countVersionsAfterDelete);
        $this->assertEquals($countProjectsBeforeCreate + 2, $countProjectsAfterDelete);
    }

    private function getDeletedData(): object
    {
        return json_decode('
        {
          "parameters": {
            "types": [
              "projects",
              "milestones",
              "tickets",
              "timesheets"
            ]
          },
          "resultsCount": 6,
          "results": {
            "projects": [
              {
                "id": 64,
                "deletedDate": "2025-10-24T11:36:08.000000Z"
              },
              {
                "id": 65,
                "deletedDate": "2025-10-24T11:36:08.000000Z"
              }
            ],
            "milestones": [
              {
                "id": 6724,
                "deletedDate": "2025-10-09T11:46:33.000000Z"
              },
              {
                "id": 6725,
                "deletedDate": "2025-10-24T11:36:08.000000Z"
              }
            ],
            "tickets": [
              {
                "id": 6723,
                "deletedDate": "2025-10-09T11:46:41.000000Z"
              },
              {
                "id": 6726,
                "deletedDate": "2025-10-24T11:36:08.000000Z"
              }
            ],
            "timesheets": [
              {
                "id": 66937,
                "deletedDate": "2025-10-09T11:49:34.000000Z"
              },
              {
                "id": 66938,
                "deletedDate": "2025-10-09T11:49:34.000000Z"
              }
            ]
          }
        }
        ', null, 512, JSON_THROW_ON_ERROR);
    }
}
