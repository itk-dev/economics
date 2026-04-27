<?php

namespace App\Tests\Unit\Service;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\IssueProduct;
use App\Entity\Product;
use App\Entity\Project;
use App\Entity\ProjectBilling;
use App\Entity\Version;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Exception\EconomicsException;
use App\Exception\InvoiceAlreadyOnRecordException;
use App\Repository\ClientRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectBillingRepository;
use App\Service\BillingService;
use App\Service\ClientHelper;
use App\Service\InvoiceEntryHelper;
use App\Service\ProjectBillingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProjectBillingServiceTest extends TestCase
{
    private ProjectBillingRepository $projectBillingRepository;
    private BillingService $billingService;
    private IssueRepository $issueRepository;
    private ClientRepository $clientRepository;
    private ClientHelper $clientHelper;
    private EntityManagerInterface $entityManager;
    private TranslatorInterface $translator;
    private InvoiceEntryHelper $invoiceEntryHelper;
    private ProjectBillingService $service;

    protected function setUp(): void
    {
        $this->projectBillingRepository = $this->createMock(ProjectBillingRepository::class);
        $this->billingService = $this->createMock(BillingService::class);
        $this->issueRepository = $this->createMock(IssueRepository::class);
        $this->clientRepository = $this->createMock(ClientRepository::class);
        $this->clientHelper = $this->createMock(ClientHelper::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);
        $this->invoiceEntryHelper = $this->createMock(InvoiceEntryHelper::class);
        $this->invoiceEntryHelper->method('getDefaultAccount')->willReturn('1234');
        $this->invoiceEntryHelper->method('getProductAccount')->willReturn('5678');

        $this->service = new ProjectBillingService(
            $this->projectBillingRepository,
            $this->billingService,
            $this->issueRepository,
            $this->clientRepository,
            $this->clientHelper,
            $this->entityManager,
            $this->translator,
            $this->invoiceEntryHelper,
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setValue($entity, $id);
    }

    private function createProjectBillingEntity(?Project $project = null): ProjectBilling
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setName('Test PB');
        $projectBilling->setPeriodStart(new \DateTime('2026-01-01'));
        $projectBilling->setPeriodEnd(new \DateTime('2026-01-31'));
        $projectBilling->setRecorded(false);
        $projectBilling->setDescription('Test description');
        if ($project) {
            $projectBilling->setProject($project);
        }

        return $projectBilling;
    }

    private function createIssueWithWorklog(bool $billed = false, int $timeSpentSeconds = 3600): Issue
    {
        $issue = new Issue();
        $issue->setName('Test Issue');
        $issue->setProjectTrackerKey('PROJ-1');
        $issue->setProjectTrackerId('100');
        $issue->setLinkToIssue('https://example.com');

        $worklog = new Worklog();
        $worklog->setWorklogId(1);
        $worklog->setWorker('worker');
        $worklog->setTimeSpentSeconds($timeSpentSeconds);
        $worklog->setStarted(new \DateTime());
        $worklog->setProjectTrackerIssueId('100');
        $worklog->setIsBilled($billed);

        $issue->addWorklog($worklog);

        return $issue;
    }

    // --- getIssuesNotIncludedInProjectBilling ---

    public function testGetIssuesNotIncludedNoProjectThrowsException(): void
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setPeriodStart(new \DateTime());
        $projectBilling->setPeriodEnd(new \DateTime());

        $this->expectException(EconomicsException::class);
        $this->service->getIssuesNotIncludedInProjectBilling($projectBilling);
    }

    public function testGetIssuesNotIncludedNoPeriodThrowsException(): void
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setProject(new Project());

        $this->expectException(EconomicsException::class);
        $this->service->getIssuesNotIncludedInProjectBilling($projectBilling);
    }

    public function testGetIssuesNotIncludedUnbilledWorklogsIncluded(): void
    {
        $project = new Project();
        $projectBilling = $this->createProjectBillingEntity($project);

        $issue = $this->createIssueWithWorklog(billed: false, timeSpentSeconds: 3600);

        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);

        $result = $this->service->getIssuesNotIncludedInProjectBilling($projectBilling);

        $this->assertCount(1, $result);
        $this->assertSame($issue, $result[0]);
    }

    public function testGetIssuesNotIncludedBilledWorklogsExcluded(): void
    {
        $project = new Project();
        $projectBilling = $this->createProjectBillingEntity($project);

        $issue = $this->createIssueWithWorklog(billed: true);

        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);

        $result = $this->service->getIssuesNotIncludedInProjectBilling($projectBilling);

        $this->assertCount(0, $result);
    }

    public function testGetIssuesNotIncludedZeroTimeWorklogsExcluded(): void
    {
        $project = new Project();
        $projectBilling = $this->createProjectBillingEntity($project);

        $issue = $this->createIssueWithWorklog(billed: false, timeSpentSeconds: 0);

        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);

        $result = $this->service->getIssuesNotIncludedInProjectBilling($projectBilling);

        $this->assertCount(0, $result);
    }

    public function testGetIssuesNotIncludedUnbilledProductsIncluded(): void
    {
        $project = new Project();
        $projectBilling = $this->createProjectBillingEntity($project);

        $issue = new Issue();
        $issue->setName('Product Issue');
        $issue->setProjectTrackerKey('PROJ-2');
        $issue->setProjectTrackerId('200');
        $issue->setLinkToIssue('https://example.com');

        $product = new IssueProduct();
        $product->setQuantity(1);
        $product->setIsBilled(false);
        $issue->addProduct($product);

        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);

        $result = $this->service->getIssuesNotIncludedInProjectBilling($projectBilling);

        $this->assertCount(1, $result);
    }

    public function testGetIssuesNotIncludedBilledProductsExcluded(): void
    {
        $project = new Project();
        $projectBilling = $this->createProjectBillingEntity($project);

        $issue = new Issue();
        $issue->setName('Product Issue');
        $issue->setProjectTrackerKey('PROJ-2');
        $issue->setProjectTrackerId('200');
        $issue->setLinkToIssue('https://example.com');

        $product = new IssueProduct();
        $product->setQuantity(1);
        $product->setIsBilled(true);
        $issue->addProduct($product);

        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);

        $result = $this->service->getIssuesNotIncludedInProjectBilling($projectBilling);

        $this->assertCount(0, $result);
    }

    // --- updateProjectBilling ---

    public function testUpdateProjectBillingNotFoundThrowsException(): void
    {
        $this->projectBillingRepository->method('find')->willReturn(null);

        $this->expectException(EconomicsException::class);
        $this->service->updateProjectBilling(999);
    }

    public function testUpdateProjectBillingRemovesNonRecordedInvoices(): void
    {
        $project = new Project();
        $project->setName('Test');
        $projectBilling = $this->createProjectBillingEntity($project);
        $this->setEntityId($projectBilling, 1);

        $recordedInvoice = new Invoice();
        $recordedInvoice->setName('Recorded');
        $recordedInvoice->setRecorded(true);
        $projectBilling->addInvoice($recordedInvoice);

        $nonRecordedInvoice = new Invoice();
        $nonRecordedInvoice->setName('Not Recorded');
        $nonRecordedInvoice->setRecorded(false);
        $projectBilling->addInvoice($nonRecordedInvoice);

        $this->projectBillingRepository->method('find')->with(1)->willReturn($projectBilling);
        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([]);

        $this->entityManager->expects($this->once())->method('remove')->with($nonRecordedInvoice);

        $this->service->updateProjectBilling(1);
    }

    // --- createProjectBilling ---

    public function testCreateProjectBillingNotFoundThrowsException(): void
    {
        $this->projectBillingRepository->method('find')->willReturn(null);

        $this->expectException(EconomicsException::class);
        $this->service->createProjectBilling(999);
    }

    public function testCreateProjectBillingNoProjectThrowsException(): void
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setPeriodStart(new \DateTime());
        $projectBilling->setPeriodEnd(new \DateTime());
        $this->setEntityId($projectBilling, 1);

        $this->projectBillingRepository->method('find')->willReturn($projectBilling);

        $this->expectException(EconomicsException::class);
        $this->service->createProjectBilling(1);
    }

    public function testCreateProjectBillingNoPeriodThrowsException(): void
    {
        $project = new Project();
        $projectBilling = new ProjectBilling();
        $projectBilling->setProject($project);
        $this->setEntityId($projectBilling, 1);

        $this->projectBillingRepository->method('find')->willReturn($projectBilling);

        $this->expectException(EconomicsException::class);
        $this->service->createProjectBilling(1);
    }

    public function testCreateProjectBillingSkipsIssuesWithoutPbVersion(): void
    {
        $project = new Project();
        $project->setName('Test');
        $projectBilling = $this->createProjectBillingEntity($project);
        $this->setEntityId($projectBilling, 1);

        // Issue with no PB- version
        $issue = $this->createIssueWithWorklog();
        $version = new Version();
        $version->setName('Sprint 1');
        $version->setProjectTrackerId('v1');
        $issue->addVersion($version);

        $this->projectBillingRepository->method('find')->willReturn($projectBilling);
        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);

        // No invoices should be created — persist should not be called for invoices
        $this->entityManager->expects($this->never())->method('persist');

        $this->service->createProjectBilling(1);
    }

    public function testCreateProjectBillingSkipsIssuesWithNoMatchingClient(): void
    {
        $project = new Project();
        $project->setName('Test');
        $projectBilling = $this->createProjectBillingEntity($project);
        $this->setEntityId($projectBilling, 1);

        $issue = $this->createIssueWithWorklog();
        $version = new Version();
        $version->setName('PB-ClientA');
        $version->setProjectTrackerId('v1');
        $issue->addVersion($version);

        $this->projectBillingRepository->method('find')->willReturn($projectBilling);
        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);
        $this->clientRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects($this->never())->method('persist');

        $this->service->createProjectBilling(1);
    }

    public function testCreateProjectBillingCreatesInvoicePerClient(): void
    {
        $project = new Project();
        $project->setName('Test');
        $projectBilling = $this->createProjectBillingEntity($project);
        $this->setEntityId($projectBilling, 1);

        $client = new Client();
        $client->setName('Client A');
        $client->setType(ClientTypeEnum::INTERNAL);
        $this->setEntityId($client, 10);

        $issue = $this->createIssueWithWorklog();
        $version = new Version();
        $version->setName('PB-ClientA');
        $version->setProjectTrackerId('v1');
        $issue->addVersion($version);

        $this->projectBillingRepository->method('find')->willReturn($projectBilling);
        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);
        $this->clientRepository->method('findOneBy')->with(['versionName' => 'PB-ClientA'])->willReturn($client);
        $this->clientHelper->method('getStandardPrice')->willReturn(500.0);

        $persistedEntities = [];
        $this->entityManager->method('persist')->willReturnCallback(function ($entity) use (&$persistedEntities) {
            $persistedEntities[] = $entity;
        });

        $this->service->createProjectBilling(1);

        // Should persist at least an InvoiceEntry and an Invoice
        $invoiceEntries = array_filter($persistedEntities, fn ($e) => $e instanceof InvoiceEntry);
        $invoices = array_filter($persistedEntities, fn ($e) => $e instanceof Invoice);

        $this->assertNotEmpty($invoiceEntries);
        $this->assertNotEmpty($invoices);
        $this->assertCount(1, $projectBilling->getInvoices());
    }

    public function testCreateProjectBillingCreatesProductInvoiceEntries(): void
    {
        $project = new Project();
        $project->setName('Test');
        $projectBilling = $this->createProjectBillingEntity($project);
        $this->setEntityId($projectBilling, 1);

        $client = new Client();
        $client->setName('Client B');
        $client->setType(ClientTypeEnum::EXTERNAL);
        $this->setEntityId($client, 20);

        $issue = new Issue();
        $issue->setName('Product Issue');
        $issue->setProjectTrackerKey('PROJ-3');
        $issue->setProjectTrackerId('300');
        $issue->setLinkToIssue('https://example.com');

        $product = new Product();
        $product->setName('Widget');
        $product->setPrice('100.00');
        $product->setProject($project);

        $issueProduct = new IssueProduct();
        $issueProduct->setProduct($product);
        $issueProduct->setQuantity(2.0);
        $issueProduct->setDescription('Two widgets');
        $issueProduct->setIsBilled(false);
        $issue->addProduct($issueProduct);

        $version = new Version();
        $version->setName('PB-ClientB');
        $version->setProjectTrackerId('v2');
        $issue->addVersion($version);

        $this->projectBillingRepository->method('find')->willReturn($projectBilling);
        $this->issueRepository->method('getClosedIssuesFromInterval')->willReturn([$issue]);
        $this->clientRepository->method('findOneBy')->willReturn($client);
        $this->clientHelper->method('getStandardPrice')->willReturn(400.0);

        $persistedEntities = [];
        $this->entityManager->method('persist')->willReturnCallback(function ($entity) use (&$persistedEntities) {
            $persistedEntities[] = $entity;
        });

        $this->service->createProjectBilling(1);

        $productEntries = array_filter($persistedEntities, fn ($e) => $e instanceof InvoiceEntry && 'Widget' === str_contains($e->getProduct() ?? '', 'Widget'));

        $this->assertNotEmpty($projectBilling->getInvoices());
    }

    // --- recordProjectBilling ---

    public function testRecordProjectBillingSetsRecordedTrue(): void
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setRecorded(false);

        $invoice = new Invoice();
        $invoice->setName('Invoice 1');
        $invoice->setRecorded(false);
        $projectBilling->addInvoice($invoice);

        $this->billingService->expects($this->once())->method('recordInvoice');
        $this->entityManager->expects($this->once())->method('flush');
        $this->projectBillingRepository->expects($this->once())->method('save');

        $this->service->recordProjectBilling($projectBilling);

        $this->assertTrue($projectBilling->isRecorded());
    }

    public function testRecordProjectBillingIgnoresAlreadyRecordedInvoices(): void
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setRecorded(false);

        $invoice = new Invoice();
        $invoice->setName('Already Recorded');
        $invoice->setRecorded(true);
        $projectBilling->addInvoice($invoice);

        $this->billingService->method('recordInvoice')
            ->willThrowException(new InvoiceAlreadyOnRecordException());

        // Should not throw — exception is caught
        $this->service->recordProjectBilling($projectBilling);

        $this->assertTrue($projectBilling->isRecorded());
    }

    // --- getInvoiceEntryProduct ---

    public function testGetInvoiceEntryProductReturnsKeyAndName(): void
    {
        $issue = new Issue();
        $issue->setName('Fix login bug');
        $issue->setProjectTrackerKey('PROJ-42');
        $issue->setProjectTrackerId('1');
        $issue->setLinkToIssue('https://example.com');

        $result = $this->service->getInvoiceEntryProduct($issue);

        $this->assertEquals('PROJ-42:Fix login bug', $result);
    }

    public function testGetInvoiceEntryProductStripsDevsuppReference(): void
    {
        $issue = new Issue();
        $issue->setName('Fix login bug (DEVSUPP-123)');
        $issue->setProjectTrackerKey('PROJ-42');
        $issue->setProjectTrackerId('1');
        $issue->setLinkToIssue('https://example.com');

        $result = $this->service->getInvoiceEntryProduct($issue);

        $this->assertEquals('PROJ-42:Fix login bug ', $result);
    }

    public function testGetInvoiceEntryProductStripsDevsuppCaseInsensitive(): void
    {
        $issue = new Issue();
        $issue->setName('Task (devsupp-456)');
        $issue->setProjectTrackerKey('KEY');
        $issue->setProjectTrackerId('1');
        $issue->setLinkToIssue('https://example.com');

        $result = $this->service->getInvoiceEntryProduct($issue);

        $this->assertEquals('KEY:Task ', $result);
    }
}
