<?php

namespace App\Tests\Integration\Service;

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
use App\Service\LeantimeUrlGenerator;
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
            new LeantimeUrlGenerator(),
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
        $this->assertEquals((new \DateTime('2024-10-03T13:47:30.000000Z'))->getTimestamp(), $project->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Project::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime('2025-01-01'));
        $after = count($projectRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $project = $projectRepository->findOneBy(['projectTrackerId' => 50]);
        $this->assertEquals((new \DateTime('2025-10-03T13:47:30.000000Z'))->getTimestamp(), $project->getSourceModifiedDate()->getTimestamp());

        // Milestones

        $before = count($versionRepository->findAll());
        $service->updateAsJob(Version::class, 0, 100, $dataProvider->getId());
        $after = count($versionRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $version = $versionRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime('2024-10-03T13:47:30.000000Z'))->getTimestamp(), $version->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Version::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime('2025-01-01'));
        $after = count($versionRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $version = $versionRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime('2025-10-03T13:47:30.000000Z'))->getTimestamp(), $version->getSourceModifiedDate()->getTimestamp());

        // Tickets

        $before = count($issueRepository->findAll());
        $service->updateAsJob(Issue::class, 0, 100, $dataProvider->getId());
        $after = count($issueRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $issue = $issueRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime('2024-10-03T13:47:30.000000Z'))->getTimestamp(), $issue->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Issue::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime('2025-01-01'));
        $after = count($issueRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $issue = $issueRepository->findOneBy(['projectTrackerId' => 10, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime('2025-10-03T13:47:30.000000Z'))->getTimestamp(), $issue->getSourceModifiedDate()->getTimestamp());

        // Timesheets

        $before = count($worklogRepository->findAll());
        $service->updateAsJob(Worklog::class, 0, 100, $dataProvider->getId());
        $after = count($worklogRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $worklog = $worklogRepository->findOneBy(['worklogId' => 1, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime('2024-10-03T13:47:30.000000Z'))->getTimestamp(), $worklog->getSourceModifiedDate()->getTimestamp());
        // Repeat process to test that no extra entries are added and test modifiedAfter
        $service->updateAsJob(Worklog::class, 0, 100, $dataProvider->getId(), [], false, new \DateTime('2025-01-01'));
        $after = count($worklogRepository->findAll());
        $this->assertEquals($before + 2, $after);
        $worklog = $worklogRepository->findOneBy(['worklogId' => 1, 'dataProvider' => $dataProvider]);
        $this->assertEquals((new \DateTime('2025-10-03T13:47:30.000000Z'))->getTimestamp(), $worklog->getSourceModifiedDate()->getTimestamp());
    }

    /**
     * Nullable fields from data-api#18 must not halt the sync.
     *
     * A row that cannot be mapped is logged and skipped; every other row still imports.
     */
    public function testUpdateWithNullValues(): void
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

        // Collect the skip messages instead of asserting call counts, so the log stays readable
        // when a new skip is added.
        $loggedErrors = [];
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->method('error')->willReturnCallback(
            function (string $message, array $context = []) use (&$loggedErrors) {
                $loggedErrors[] = $message;
            }
        );

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $responses = [];

        foreach ([$this->getNullValueProjects(), $this->getNullValueMilestones(), $this->getNullValueTickets(), $this->getNullValueTimesheets()] as $payload) {
            $responseMock = $this->createMock(ResponseInterface::class);
            $responseMock->method('getStatusCode')->willReturn(200);
            $responseMock->method('getContent')->willReturn(json_encode($payload));
            $responses[] = $responseMock;
        }

        $httpClientMock->method('request')->willReturn(...$responses);

        $service = new LeantimeApiService(
            $httpClientMock,
            $messageBus,
            $dataProviderRepository,
            $entityManager,
            $projectRepository,
            $loggerMock,
            new LeantimeUrlGenerator(),
        );

        $dataProvider = new DataProvider();
        $dataProvider->setName('Data Provider 5 - null values');
        $dataProvider->setEnabled(true);
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('http://localhost/');
        $dataProvider->setSecret('Not so secret');
        $entityManager->persist($dataProvider);
        $entityManager->flush();

        // A project with no name is kept under a placeholder; dropping it would orphan its issues.
        // The tracker id is part of the placeholder so two unnamed entities stay distinguishable.
        $before = count($projectRepository->findAll());
        $service->updateAsJob(Project::class, 0, 100, $dataProvider->getId());
        $this->assertEquals($before + 2, count($projectRepository->findAll()));
        $this->assertEquals('(no name) 70', $projectRepository->findOneBy(['projectTrackerId' => 70, 'dataProvider' => $dataProvider])->getName());

        // The nameless milestone is kept, the one without a project is skipped: Version::$project
        // is not nullable.
        $before = count($versionRepository->findAll());
        $service->updateAsJob(Version::class, 0, 100, $dataProvider->getId());
        $this->assertEquals($before + 1, count($versionRepository->findAll()));
        $this->assertEquals('(no name) 20', $versionRepository->findOneBy(['projectTrackerId' => 20, 'dataProvider' => $dataProvider])->getName());
        $this->assertNull($versionRepository->findOneBy(['projectTrackerId' => 21, 'dataProvider' => $dataProvider]));

        // A null status maps to OTHER, and null hours are stored as null rather than raising. The
        // ticket without a project is skipped rather than stored against no project at all, which
        // would also clear the association on any issue that already had one.
        $before = count($issueRepository->findAll());
        $service->updateAsJob(Issue::class, 0, 100, $dataProvider->getId());
        $this->assertEquals($before + 2, count($issueRepository->findAll()));
        $issue = $issueRepository->findOneBy(['projectTrackerId' => 30, 'dataProvider' => $dataProvider]);
        $this->assertEquals('(no name) 30', $issue->getName());
        $this->assertEquals(IssueStatusEnum::OTHER, $issue->getStatus());
        $this->assertNull($issue->getPlanHours());
        $this->assertNull($issueRepository->findOneBy(['projectTrackerId' => 32, 'dataProvider' => $dataProvider]));

        // Hours worked by a deleted user are real billable data, so the worklog is kept and
        // attributed via the userId that data-api#18 added. The one with no ticket cannot be
        // stored at all, as Worklog::$issue is not nullable.
        $before = count($worklogRepository->findAll());
        $service->updateAsJob(Worklog::class, 0, 100, $dataProvider->getId());
        $this->assertEquals($before + 2, count($worklogRepository->findAll()));

        $deletedUserWorklog = $worklogRepository->findOneBy(['worklogId' => 100, 'dataProvider' => $dataProvider]);
        $this->assertEquals('deleted-user-42', $deletedUserWorklog->getWorker());
        $this->assertNull($deletedUserWorklog->getKind());

        $this->assertNull($worklogRepository->findOneBy(['worklogId' => 101, 'dataProvider' => $dataProvider]));

        $unknownUserWorklog = $worklogRepository->findOneBy(['worklogId' => 102, 'dataProvider' => $dataProvider]);
        $this->assertEquals('deleted-user-unknown', $unknownUserWorklog->getWorker());

        // Rows 103 and 104 are the regression probes for the two ways a single row used to stop
        // the sync: a TypeError, which catch (\Exception) could not see, and a handler failure,
        // which happened outside the try because the dispatch sat there.
        $this->assertNull($worklogRepository->findOneBy(['worklogId' => 103, 'dataProvider' => $dataProvider]));
        $this->assertNull($worklogRepository->findOneBy(['worklogId' => 104, 'dataProvider' => $dataProvider]));

        // Each skipped row is reported once, so a halt could never be silent.
        $this->assertCount(5, $loggedErrors);
        $this->assertStringContainsString('Version upsert not acceptable: projectId is null', $loggedErrors[0]);
        $this->assertStringContainsString('Issue upsert not acceptable: projectId is null', $loggedErrors[1]);
        $this->assertStringContainsString('ticketId is null', $loggedErrors[2]);
        $this->assertStringContainsString('Skipping App\Entity\Worklog id 103', $loggedErrors[3]);
        $this->assertStringContainsString('999', $loggedErrors[4]);
    }

    /**
     * A stand-in username must not replace a worker name already on record.
     *
     * Keeping the worklog is worth a placeholder; overwriting a name an earlier sync stored is not.
     */
    public function testDeletedUserFallbackKeepsStoredWorker(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $messageBus = $container->get(MessageBusInterface::class);
        $dataProviderRepository = $container->get(DataProviderRepository::class);
        $projectRepository = $container->get(ProjectRepository::class);
        $worklogRepository = $container->get(WorklogRepository::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        $loggerMock = $this->createMock(LoggerInterface::class);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getContent')->willReturn(json_encode($this->getDeletedUserTimesheets()));

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock->method('request')->willReturn($responseMock);

        $service = new LeantimeApiService(
            $httpClientMock,
            $messageBus,
            $dataProviderRepository,
            $entityManager,
            $projectRepository,
            $loggerMock,
            new LeantimeUrlGenerator(),
        );

        $dataProvider = new DataProvider();
        $dataProvider->setName('Data Provider 6 - deleted user');
        $dataProvider->setEnabled(true);
        $dataProvider->setClass(LeantimeApiService::class);
        $dataProvider->setUrl('http://localhost/');
        $dataProvider->setSecret('Not so secret');
        $entityManager->persist($dataProvider);

        $project = new Project();
        $project->setDataProvider($dataProvider);
        $project->setProjectTrackerId(80);
        $project->setProjectTrackerKey(80);
        $project->setName('Project for a departed user');
        $project->setProjectTrackerProjectUrl('http://localhost/');
        $project->setInclude(true);
        $project->setIsBillable(true);
        $entityManager->persist($project);

        $issue = new Issue();
        $issue->setDataProvider($dataProvider);
        $issue->setProject($project);
        $issue->setProjectTrackerId(40);
        $issue->setProjectTrackerKey(40);
        $issue->setName('Issue for a departed user');
        $issue->setStatus(IssueStatusEnum::DONE);
        $issue->setLinkToIssue('www.example.com');
        $entityManager->persist($issue);

        $worklog = new Worklog();
        $worklog->setDataProvider($dataProvider);
        $worklog->setProject($project);
        $worklog->setIssue($issue);
        $worklog->setProjectTrackerIssueId(40);
        $worklog->setWorklogId(200);
        $worklog->setDescription('Recorded while the user still existed');
        $worklog->setIsBilled(false);
        $worklog->setWorker('real.person@example.com');
        $worklog->setTimeSpentSeconds(60 * 60);
        $worklog->setStarted(new \DateTime('2026-01-04T22:00:00.000000Z'));
        $worklog->setKind(BillableKindsEnum::GENERAL_BILLABLE);
        $entityManager->persist($worklog);
        $entityManager->flush();

        $service->updateAsJob(Worklog::class, 0, 100, $dataProvider->getId());

        $stored = $worklogRepository->findOneBy(['worklogId' => 200, 'dataProvider' => $dataProvider]);

        // The rest of the row was upserted, so the sync did run over it — only the worker was left.
        $this->assertEquals(60 * 60 * 3, $stored->getTimeSpentSeconds());
        $this->assertEquals('real.person@example.com', $stored->getWorker());
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

        // One response per type: the endpoint serves a single type's page per request.
        $responseMocks = [];

        foreach ($this->getDeletedData() as $type => $payload) {
            $responseMock = $this->createMock(ResponseInterface::class);
            $responseMock->method('getStatusCode')->willReturn(200);
            $responseMock->method('getContent')->willReturn(json_encode($payload));

            $responseMocks[$type] = $responseMock;
        }

        // Capture what was actually sent, so the request bodies can be asserted after the calls.
        $requestJson = [];
        $httpClientMock->expects($this->exactly(count($responseMocks)))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use ($responseMocks, &$requestJson): ResponseInterface {
                $requestJson[] = $options['json'] ?? null;

                return $responseMocks[$options['json']['type']];
            });

        $service = new LeantimeApiService(
            $httpClientMock,
            $messageBus,
            $dataProviderRepository,
            $entityManager,
            $projectRepository,
            $loggerMock,
            new LeantimeUrlGenerator(),
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
        $version1->setName('Version 1');
        $version1->setProject($project1);
        $version1->setProjectTrackerId(6724);
        $entityManager->persist($version1);

        $version2 = new Version();
        $version2->setDataProvider($dataProvider);
        $version2->setName('Version 2');
        $version2->setProject($project2);
        $version2->setProjectTrackerId(6725);
        $entityManager->persist($version2);

        $issue1 = new Issue();
        $issue1->setDataProvider($dataProvider);
        $issue1->setProject($project1);
        $issue1->setProjectTrackerId(6723);
        $issue1->setProjectTrackerKey(6723);
        $issue1->setName('issue 1 - protected');
        $issue1->setAccountId('Account 1');
        $issue1->setAccountKey('Account 1');
        $issue1->setStatus(IssueStatusEnum::DONE);
        $issue1->setDataProvider($dataProvider);
        $issue1->addVersion($version1);
        $issue1->setResolutionDate(new \DateTime());
        $issue1->setPlanHours(1);
        $issue1->setHoursRemaining(1);
        $issue1->setWorker('admin@example.com');
        $issue1->setDueDate(new \DateTime());
        $issue1->setLinkToIssue('www.example.com');
        $entityManager->persist($issue1);

        $issue2 = new Issue();
        $issue2->setDataProvider($dataProvider);
        $issue2->setProject($project1);
        $issue2->setProjectTrackerId(6726);
        $issue2->setProjectTrackerKey(6726);
        $issue2->setName('issue 2');
        $issue2->setAccountId('Account 1');
        $issue2->setAccountKey('Account 1');
        $issue2->setStatus(IssueStatusEnum::DONE);
        $issue2->setDataProvider($dataProvider);
        $issue2->addVersion($version1);
        $issue2->setResolutionDate(new \DateTime());
        $issue2->setPlanHours(1);
        $issue2->setHoursRemaining(1);
        $issue2->setWorker('admin@example.com');
        $issue2->setDueDate(new \DateTime());
        $issue2->setLinkToIssue('www.example.com');
        $entityManager->persist($issue2);

        $worklog1 = new Worklog();
        $worklog1->setProject($project1);
        $worklog1->setDataProvider($dataProvider);
        $worklog1->setProjectTrackerIssueId(6723);
        $worklog1->setWorklogId(66937);
        $worklog1->setDescription('Beskrivelse af worklog - protected');
        $worklog1->setIsBilled(false);
        $worklog1->setWorker('admin@example.com');
        $worklog1->setTimeSpentSeconds(60 * 15);
        $worklog1->setStarted(\DateTime::createFromFormat('U', (string) strtotime('2024-01-01'), new \DateTimeZone('Europe/Copenhagen')));
        $worklog1->setIssue($issue1);
        $worklog1->setDataProvider($dataProvider);
        $worklog1->setKind(BillableKindsEnum::GENERAL_BILLABLE);
        $entityManager->persist($worklog1);

        $worklog2 = new Worklog();
        $worklog2->setProject($project1);
        $worklog2->setDataProvider($dataProvider);
        $worklog2->setProjectTrackerIssueId(6726);
        $worklog2->setWorklogId(66938);
        $worklog2->setDescription('Beskrivelse af worklog');
        $worklog2->setIsBilled(false);
        $worklog2->setWorker('admin@example.com');
        $worklog2->setTimeSpentSeconds(60 * 15);
        $worklog2->setStarted(\DateTime::createFromFormat('U', (string) strtotime('2024-01-01'), new \DateTimeZone('Europe/Copenhagen')));
        $worklog2->setIssue($issue2);
        $worklog2->setDataProvider($dataProvider);
        $worklog2->setKind(BillableKindsEnum::GENERAL_BILLABLE);
        $entityManager->persist($worklog2);

        $entityManager->flush();

        $worklogId1 = $worklog1->getId();
        $issueId1 = $issue1->getId();
        $projectId1 = $project1->getId();

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
        $invoice->setName('Invoice 1');
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

        $deletedAfter = new \DateTime('2025-10-06T11:36:08.000000Z');

        // Stands in for delete()'s dispatch: one request per type, children before the parents they
        // hang off. Calling it in a loop is what the sync transport does anyway — asyncJobQueue is
        // false below, so every removal is handled inline before the next type starts, which is the
        // ordering the assertions further down rely on.
        foreach ([LeantimeApiService::TIMESHEETS, LeantimeApiService::TICKETS, LeantimeApiService::MILESTONES, LeantimeApiService::PROJECTS] as $type) {
            $service->deleteAsJob($type, 0, 100, $id, false, $deletedAfter);
        }

        // The plugin only reads 'deletedAfter'. Under any other key the timestamp is silently
        // discarded and every run pages through the entire deletion history.
        $this->assertSame(
            [
                ['type' => 'timesheets', 'start' => 0, 'limit' => 100, 'deletedAfter' => $deletedAfter->getTimestamp()],
                ['type' => 'tickets', 'start' => 0, 'limit' => 100, 'deletedAfter' => $deletedAfter->getTimestamp()],
                ['type' => 'milestones', 'start' => 0, 'limit' => 100, 'deletedAfter' => $deletedAfter->getTimestamp()],
                ['type' => 'projects', 'start' => 0, 'limit' => 100, 'deletedAfter' => $deletedAfter->getTimestamp()],
            ],
            $requestJson
        );

        $countProjectsAfterDelete = count($projectRepository->findAll());
        $countVersionsAfterDelete = count($versionRepository->findAll());
        $countIssuesAfterDelete = count($issueRepository->findAll());
        $countWorklogsAfterDelete = count($worklogRepository->findAll());

        // The two worklogs sit behind an entry with an unparsable date. If that entry escaped the
        // loop instead of being skipped, neither would ever be reached.
        $this->assertEquals($countWorklogsBeforeCreate + 1, $countWorklogsAfterDelete);
        $this->assertEquals($countIssuesBeforeCreate + 1, $countIssuesAfterDelete);
        // Versions can always be removed.
        $this->assertEquals($countVersionsBeforeCreate, $countVersionsAfterDelete);
        $this->assertEquals($countProjectsBeforeCreate + 1, $countProjectsAfterDelete);

        $project1 = $projectRepository->find($projectId1);
        $this->assertEquals(new \DateTime('2025-10-24T11:36:08.000000Z'), $project1->getSourceDeletedDate());

        $issue1 = $issueRepository->find($issueId1);
        $this->assertEquals(new \DateTime('2025-10-24T11:36:08.000000Z'), $issue1->getSourceDeletedDate());

        $worklog1 = $worklogRepository->find($worklogId1);
        $this->assertEquals(new \DateTime('2025-10-24T11:36:08.000000Z'), $worklog1->getSourceDeletedDate());
    }

    private function getNullValueProjects(): object
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
                  "id": 70,
                  "name": null,
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 71,
                  "name": "Project with a name",
                  "modified": "2026-01-05T09:00:00.000000Z"
                }
              ]
            }
        ', null, 512, JSON_THROW_ON_ERROR);
    }

    private function getNullValueMilestones(): object
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
                  "id": 20,
                  "projectId": 70,
                  "name": null,
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 21,
                  "projectId": null,
                  "name": "Milestone without a project",
                  "modified": "2026-01-05T09:00:00.000000Z"
                }
              ]
            }
        ', null, 512, JSON_THROW_ON_ERROR);
    }

    private function getNullValueTickets(): object
    {
        return json_decode('
            {
              "parameters": {
                "start": 0,
                "limit": 100
              },
              "resultsCount": 3,
              "results": [
                {
                  "id": 30,
                  "projectId": 70,
                  "name": null,
                  "status": null,
                  "milestoneId": null,
                  "tags": [],
                  "worker": null,
                  "plannedHours": null,
                  "remainingHours": null,
                  "dueDate": null,
                  "resolutionDate": null,
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 31,
                  "projectId": 70,
                  "name": "Ticket with a name",
                  "status": "DONE",
                  "milestoneId": null,
                  "tags": [],
                  "worker": "admin@example.com",
                  "plannedHours": 4,
                  "remainingHours": 2,
                  "dueDate": null,
                  "resolutionDate": null,
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 32,
                  "projectId": null,
                  "name": "Ticket without a project",
                  "status": "NEW",
                  "milestoneId": null,
                  "tags": [],
                  "worker": null,
                  "plannedHours": null,
                  "remainingHours": null,
                  "dueDate": null,
                  "resolutionDate": null,
                  "modified": "2026-01-05T09:00:00.000000Z"
                }
              ]
            }
        ', null, 512, JSON_THROW_ON_ERROR);
    }

    private function getNullValueTimesheets(): object
    {
        return json_decode('
            {
              "parameters": {
                "start": 0,
                "limit": 100
              },
              "resultsCount": 5,
              "results": [
                {
                  "id": 100,
                  "ticketId": 30,
                  "projectId": 70,
                  "description": "Hours worked by a since deleted user",
                  "hours": 2.5,
                  "userId": 42,
                  "username": null,
                  "kind": null,
                  "workDate": "2026-01-04T22:00:00.000000Z",
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 101,
                  "ticketId": null,
                  "projectId": null,
                  "description": "Timesheet with no ticket",
                  "hours": 1,
                  "userId": 1,
                  "username": "admin@example.com",
                  "kind": "GENERAL_BILLABLE",
                  "workDate": "2026-01-04T22:00:00.000000Z",
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 102,
                  "ticketId": 31,
                  "projectId": 70,
                  "description": "Deleted user without a userId",
                  "hours": 3,
                  "userId": null,
                  "username": null,
                  "kind": "TESTING",
                  "workDate": "2026-01-04T22:00:00.000000Z",
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 103,
                  "ticketId": 31,
                  "projectId": 70,
                  "description": "Null in a field the API declares non-nullable",
                  "hours": null,
                  "userId": 1,
                  "username": "admin@example.com",
                  "kind": "GENERAL_BILLABLE",
                  "workDate": "2026-01-04T22:00:00.000000Z",
                  "modified": "2026-01-05T09:00:00.000000Z"
                },
                {
                  "id": 104,
                  "ticketId": 999,
                  "projectId": 70,
                  "description": "References a ticket that was never synced",
                  "hours": 1,
                  "userId": 1,
                  "username": "admin@example.com",
                  "kind": "GENERAL_BILLABLE",
                  "workDate": "2026-01-04T22:00:00.000000Z",
                  "modified": "2026-01-05T09:00:00.000000Z"
                }
              ]
            }
        ', null, 512, JSON_THROW_ON_ERROR);
    }

    private function getDeletedUserTimesheets(): object
    {
        return json_decode('
            {
              "parameters": {
                "start": 0,
                "limit": 100
              },
              "resultsCount": 1,
              "results": [
                {
                  "id": 200,
                  "ticketId": 40,
                  "projectId": 80,
                  "description": "Recorded while the user still existed",
                  "hours": 3,
                  "userId": 42,
                  "username": null,
                  "kind": "GENERAL_BILLABLE",
                  "workDate": "2026-01-04T22:00:00.000000Z",
                  "modified": "2026-01-06T09:00:00.000000Z"
                }
              ]
            }
        ', null, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * One response per type, keyed by the type it answers. `deletionId` is the deletion's own id,
     * which the results are ordered and paged on; `id` is the entity that was deleted.
     *
     * @return array<string, object>
     */
    private function getDeletedData(): array
    {
        return [
            'projects' => json_decode('
            {
              "parameters": {"type": "projects", "start": 0, "limit": 100},
              "resultsCount": 2,
              "results": [
                {
                  "deletionId": 1,
                  "id": 64,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                },
                {
                  "deletionId": 2,
                  "id": 65,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                }
              ]
            }
            ', null, 512, JSON_THROW_ON_ERROR),
            'milestones' => json_decode('
            {
              "parameters": {"type": "milestones", "start": 0, "limit": 100},
              "resultsCount": 2,
              "results": [
                {
                  "deletionId": 1,
                  "id": 6724,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                },
                {
                  "deletionId": 3,
                  "id": 6725,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                }
              ]
            }
            ', null, 512, JSON_THROW_ON_ERROR),
            'tickets' => json_decode('
            {
              "parameters": {"type": "tickets", "start": 0, "limit": 100},
              "resultsCount": 2,
              "results": [
                {
                  "deletionId": 2,
                  "id": 6723,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                },
                {
                  "deletionId": 4,
                  "id": 6726,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                }
              ]
            }
            ', null, 512, JSON_THROW_ON_ERROR),
            'timesheets' => json_decode('
            {
              "parameters": {"type": "timesheets", "start": 0, "limit": 100},
              "resultsCount": 4,
              "results": [
                {
                  "deletionId": 1,
                  "id": null,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                },
                {
                  "deletionId": 2,
                  "id": 66939,
                  "deletedDate": "not a date"
                },
                {
                  "deletionId": 3,
                  "id": 66937,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                },
                {
                  "deletionId": 4,
                  "id": 66938,
                  "deletedDate": "2025-10-24T11:36:08.000000Z"
                }
              ]
            }
            ', null, 512, JSON_THROW_ON_ERROR),
        ];
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
                  "userId": 1,
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
                  "userId": 1,
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
