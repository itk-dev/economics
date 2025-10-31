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
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LeantimeApiServiceTest extends KernelTestCase
{
    public function testUpdate(): void
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

        $responseMock1 = $this->createMock(ResponseInterface::class);
        $responseMock1->method('getStatusCode')->willReturn(200);
        $responseMock1->method('getContent')->willReturn(json_encode($this->getProjects()));
        $responseMock1modified = $this->createMock(ResponseInterface::class);
        $responseMock1modified->method('getStatusCode')->willReturn(200);
        $responseMock1modified->method('getContent')->willReturn(json_encode($this->getProjects(2025)));
        $responseMock2 = $this->createMock(ResponseInterface::class);
        $responseMock2->method('getStatusCode')->willReturn(200);
        $responseMock2->method('getContent')->willReturn(json_encode($this->getMilestones()));
        $responseMock2modified = $this->createMock(ResponseInterface::class);
        $responseMock2modified->method('getStatusCode')->willReturn(200);
        $responseMock2modified->method('getContent')->willReturn(json_encode($this->getMilestones(2025)));
        $responseMock3 = $this->createMock(ResponseInterface::class);
        $responseMock3->method('getStatusCode')->willReturn(200);
        $responseMock3->method('getContent')->willReturn(json_encode($this->getTickets()));
        $responseMock3modified = $this->createMock(ResponseInterface::class);
        $responseMock3modified->method('getStatusCode')->willReturn(200);
        $responseMock3modified->method('getContent')->willReturn(json_encode($this->getTickets(2025)));
        $responseMock4 = $this->createMock(ResponseInterface::class);
        $responseMock4->method('getStatusCode')->willReturn(200);
        $responseMock4->method('getContent')->willReturn(json_encode($this->getTimesheets()));
        $responseMock4modified = $this->createMock(ResponseInterface::class);
        $responseMock4modified->method('getStatusCode')->willReturn(200);
        $responseMock4modified->method('getContent')->willReturn(json_encode($this->getTimesheets(2025)));

        $httpClientMock->method('request')->willReturn(
            $responseMock1,
            $responseMock1modified,
            $responseMock2,
            $responseMock2modified,
            $responseMock3,
            $responseMock3modified,
            $responseMock4,
            $responseMock4modified,
        );

        $service = new LeantimeApiService(
            $httpClientMock,
            $messageBus,
            $dataProviderRepository,
            $entityManager,
            $projectRepository,
            $loggerMock,
        );

        $dataProvider = new DataProvider();
        $dataProvider->setName('Data Provider 4');
        $dataProvider->setEnabled(true);
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('http://localhost/');
        $dataProvider->setSecret('Not so secret');
        $entityManager->persist($dataProvider);
        $entityManager->flush();

        // Projects

        $before = count($projectRepository->findAll());
        $service->updateAsJob(Project::class, 0, 100, $dataProvider->getId());
        $after = count($projectRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $project = $projectRepository->findOneBy(['projectTrackerId' => 50]);
        $this->assertEquals((new \DateTime("2024-10-03T13:47:30.000000Z"))->getTimestamp(), $project->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Project::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime("2025-01-01"));
        $after = count($projectRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $project = $projectRepository->findOneBy(['projectTrackerId' => 50]);
        $this->assertEquals((new \DateTime("2025-10-03T13:47:30.000000Z"))->getTimestamp(), $project->getSourceModifiedDate()->getTimestamp());

        // Milestones

        $before = count($versionRepository->findAll());
        $service->updateAsJob(Version::class, 0, 100, $dataProvider->getId());
        $after = count($versionRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $version = $versionRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime("2024-10-03T13:47:30.000000Z"))->getTimestamp(), $version->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Version::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime("2025-01-01"));
        $after = count($versionRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $version = $versionRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime("2025-10-03T13:47:30.000000Z"))->getTimestamp(), $version->getSourceModifiedDate()->getTimestamp());

        // Tickets

        $before = count($issueRepository->findAll());
        $service->updateAsJob(Issue::class, 0, 100, $dataProvider->getId());
        $after = count($issueRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $issue = $issueRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime("2024-10-03T13:47:30.000000Z"))->getTimestamp(), $issue->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Issue::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime("2025-01-01"));
        $after = count($issueRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $issue = $issueRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime("2025-10-03T13:47:30.000000Z"))->getTimestamp(), $issue->getSourceModifiedDate()->getTimestamp());

        // Timesheets

        $before = count($worklogRepository->findAll());
        $service->updateAsJob(Worklog::class, 0, 100, $dataProvider->getId());
        $after = count($worklogRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $worklog = $worklogRepository->findOneBy(['worklogId' => 1, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime("2024-10-03T13:47:30.000000Z"))->getTimestamp(), $worklog->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Worklog::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime("2025-01-01"));
        $after = count($worklogRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $worklog = $worklogRepository->findOneBy(['worklogId' => 1, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime("2025-10-03T13:47:30.000000Z"))->getTimestamp(), $worklog->getSourceModifiedDate()->getTimestamp());
    }

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
        $version2->setName("Version 2");
        $version2->setProject($project2);
        $version2->setProjectTrackerId(6725);
        $entityManager->persist($version2);

        $issue1 = new Issue();
        $issue1->setDataProvider($dataProvider);
        $issue1->setProject($project1);
        $issue1->setProjectTrackerId(6723);
        $issue1->setProjectTrackerKey(6723);
        $issue1->setName("issue 1 - protected");
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
        $worklog1->setDescription("Beskrivelse af worklog - protected");
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
        $invoice->setProject($project1);
        $invoice->setName("Invoice 1");
        $invoice->setRecorded(false);
        $entityManager->persist($invoice);

        $invoiceEntry = new InvoiceEntry();
        $invoiceEntry->setInvoice($invoice);
        $invoiceEntry->setEntryType(InvoiceEntryTypeEnum::WORKLOG);
        $invoiceEntry->setIndex(1);
        $invoiceEntry->addWorklog($worklog1);
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
        $this->assertEquals($countProjectsBeforeCreate + 1, $countProjectsAfterDelete);
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

    private function getProjects($modifiedYear = 2024): object
    {
        return json_decode('
            {
              "parameters": {
                "start": 0,
                "limit": 100
              },
              "resultsCount": 2,
              "results": [
                {
                  "id": 50,
                  "name": "123",
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                },
                {
                  "id": 51,
                  "name": "Lorem 1a",
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                }
              ]
            }
        ');
    }

    private function getMilestones($modifiedYear = 2024): object
    {
        return json_decode('
            {
              "parameters": {
                "start": 0,
                "limit": 100
              },
              "resultsCount": 2,
              "results": [
                {
                  "id": 10,
                  "projectId": 50,
                  "name": "Milepæl 1a",
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                },
                {
                  "id": 11,
                  "projectId": 51,
                  "name": "Den fede del",
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                }
              ]
            }
        ');
    }

    private function getTickets($modifiedYear = 2024): object
    {
        return json_decode('
            {
              "parameters": {
                "start": 0,
                "limit": 100
              },
              "resultsCount": 2,
              "results": [
                {
                  "id": 10,
                  "projectId": 50,
                  "name": "Getting Started with Leantime",
                  "status": "DONE",
                  "milestoneId": null,
                  "tags": [],
                  "worker": "admin@example.com",
                  "plannedHours": 0,
                  "remainingHours": 0,
                  "dueDate": "2024-08-08T00:00:00.000000Z",
                  "resolutionDate": "1969-12-31T00:00:00.000000Z",
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                },
                {
                  "id": 11,
                  "projectId": 51,
                  "name": "Nyt opgave 1a",
                  "status": "NEW",
                  "milestoneId": null,
                  "tags": [],
                  "worker": "admin@example.com",
                  "plannedHours": 10,
                  "remainingHours": 5,
                  "dueDate": null,
                  "resolutionDate": null,
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                }
              ]
            }
        ');
    }

    private function getTimesheets($modifiedYear = 2024): object
    {
        return json_decode('
            {
              "parameters": {
                "start": 0,
                "limit": 100
              },
              "resultsCount": 2,
              "results": [
                {
                  "id": 1,
                  "ticketId": 10,
                  "projectId": 50,
                  "description": "Fisk",
                  "hours": 5.5,
                  "kind": "GENERAL_BILLABLE",
                  "username": "admin@example.com",
                  "workDate": "2024-09-23T22:00:00.000000Z",
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                },
                {
                  "id": 2,
                  "ticketId": 11,
                  "projectId": 51,
                  "description": "add",
                  "hours": 1,
                  "kind": "GENERAL_BILLABLE",
                  "username": "admin@example.com",
                  "workDate": "2024-09-24T22:00:00.000000Z",
                  "modified": "'.$modifiedYear.'-10-03T13:47:30.000000Z"
                }
              ]
            }
        ');
    }
}
