<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\DataProvider;
use App\Entity\Invoice;
use App\Entity\Issue;
use App\Entity\Product;
use App\Entity\Project;
use App\Entity\ProjectBilling;
use App\Entity\Version;
use App\Entity\Worker;
use App\Entity\Worklog;
use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project();
    }

    public function testCollectionsStartEmpty(): void
    {
        $this->assertCount(0, $this->project->getInvoices());
        $this->assertCount(0, $this->project->getClients());
        $this->assertCount(0, $this->project->getVersions());
        $this->assertCount(0, $this->project->getWorklogs());
        $this->assertCount(0, $this->project->getProjectBillings());
        $this->assertCount(0, $this->project->getIssues());
        $this->assertCount(0, $this->project->getProducts());
        $this->assertCount(0, $this->project->getCodeowners());
        $this->assertCount(0, $this->project->getServiceAgreements());
    }

    public function testScalarAccessors(): void
    {
        $this->project->setName('Economics');
        $this->project->setProjectTrackerProjectUrl('https://tracker.example/projects/1');
        $this->project->setProjectTrackerKey('ECON');
        $this->project->setProjectTrackerId('1');
        $this->project->setInclude(true);
        $this->project->setProjectLeadName('Lead Person');
        $this->project->setProjectLeadMail('lead@example.com');
        $this->project->setIsBillable(true);
        $this->project->setHolidayPlanning(false);
        $this->project->setGithubRepos("itk-dev/economics\nitk-dev/other");

        $this->assertSame('Economics', $this->project->getName());
        $this->assertSame('https://tracker.example/projects/1', $this->project->getProjectTrackerProjectUrl());
        $this->assertSame('ECON', $this->project->getProjectTrackerKey());
        $this->assertSame('1', $this->project->getProjectTrackerId());
        $this->assertTrue($this->project->isInclude());
        $this->assertSame('Lead Person', $this->project->getProjectLeadName());
        $this->assertSame('lead@example.com', $this->project->getProjectLeadMail());
        $this->assertTrue($this->project->isBillable());
        $this->assertFalse($this->project->isHolidayPlanning());
        $this->assertSame("itk-dev/economics\nitk-dev/other", $this->project->getGithubRepos());
    }

    public function testNullableAccessorsAcceptNull(): void
    {
        $this->project->setProjectTrackerProjectUrl(null);
        $this->project->setProjectTrackerKey(null);
        $this->project->setProjectTrackerId(null);
        $this->project->setInclude(null);
        $this->project->setProjectLeadName(null);
        $this->project->setProjectLeadMail(null);
        $this->project->setIsBillable(null);
        $this->project->setHolidayPlanning(null);
        $this->project->setGithubRepos(null);

        $this->assertNull($this->project->getProjectTrackerProjectUrl());
        $this->assertNull($this->project->getProjectTrackerKey());
        $this->assertNull($this->project->getProjectTrackerId());
        $this->assertNull($this->project->isInclude());
        $this->assertNull($this->project->getProjectLeadName());
        $this->assertNull($this->project->getProjectLeadMail());
        $this->assertNull($this->project->isBillable());
        $this->assertNull($this->project->isHolidayPlanning());
        $this->assertNull($this->project->getGithubRepos());
    }

    public function testToStringUsesName(): void
    {
        $this->project->setName('Economics');

        $this->assertSame('Economics', (string) $this->project);
    }

    public function testToStringFallsBackToIdWhenNameIsMissing(): void
    {
        $this->assertSame('', (string) $this->project);
    }

    public function testDataProviderAccessor(): void
    {
        $dataProvider = new DataProvider();
        $this->project->setDataProvider($dataProvider);

        $this->assertSame($dataProvider, $this->project->getDataProvider());
    }

    public function testSynchronizationDates(): void
    {
        $fetched = new \DateTime('2026-01-01');
        $modified = new \DateTime('2026-02-01');
        $deleted = new \DateTime('2026-03-01');

        $this->project->setFetchDate($fetched);
        $this->project->setSourceModifiedDate($modified);
        $this->project->setSourceDeletedDate($deleted);

        $this->assertSame($fetched, $this->project->getFetchDate());
        $this->assertSame($modified, $this->project->getSourceModifiedDate());
        $this->assertSame($deleted, $this->project->getSourceDeletedDate());
    }

    public function testAddInvoiceSetsOwningSide(): void
    {
        $invoice = new Invoice();
        $this->project->addInvoice($invoice);

        $this->assertCount(1, $this->project->getInvoices());
        $this->assertSame($this->project, $invoice->getProject());
    }

    public function testAddInvoiceIsIdempotent(): void
    {
        $invoice = new Invoice();
        $this->project->addInvoice($invoice);
        $this->project->addInvoice($invoice);

        $this->assertCount(1, $this->project->getInvoices());
    }

    public function testRemoveInvoiceClearsOwningSide(): void
    {
        $invoice = new Invoice();
        $this->project->addInvoice($invoice);
        $this->project->removeInvoice($invoice);

        $this->assertCount(0, $this->project->getInvoices());
        $this->assertNull($invoice->getProject());
    }

    public function testRemoveInvoiceLeavesForeignOwnerAlone(): void
    {
        $other = new Project();
        $invoice = new Invoice();
        $other->addInvoice($invoice);
        $this->project->getInvoices()->add($invoice);

        $this->project->removeInvoice($invoice);

        $this->assertSame($other, $invoice->getProject());
    }

    public function testClientsAreManagedWithoutTouchingTheInverseSide(): void
    {
        $client = new Client();

        $this->project->addClient($client);
        $this->project->addClient($client);
        $this->assertCount(1, $this->project->getClients());
        $this->assertCount(0, $client->getProjects());

        $this->project->removeClient($client);
        $this->assertCount(0, $this->project->getClients());
    }

    public function testAddVersionSetsOwningSide(): void
    {
        $version = new Version();
        $this->project->addVersion($version);
        $this->project->addVersion($version);

        $this->assertCount(1, $this->project->getVersions());
        $this->assertSame($this->project, $version->getProject());
    }

    public function testRemoveVersionClearsOwningSide(): void
    {
        $version = new Version();
        $this->project->addVersion($version);
        $this->project->removeVersion($version);

        $this->assertCount(0, $this->project->getVersions());
        $this->assertNull($version->getProject());
    }

    public function testAddWorklogSetsOwningSide(): void
    {
        $worklog = new Worklog();
        $this->project->addWorklog($worklog);
        $this->project->addWorklog($worklog);

        $this->assertCount(1, $this->project->getWorklogs());
        $this->assertSame($this->project, $worklog->getProject());
    }

    public function testRemoveWorklogClearsOwningSide(): void
    {
        $worklog = new Worklog();
        $this->project->addWorklog($worklog);
        $this->project->removeWorklog($worklog);

        $this->assertCount(0, $this->project->getWorklogs());
        $this->assertNull($worklog->getProject());
    }

    public function testAddProjectBillingSetsOwningSide(): void
    {
        $billing = new ProjectBilling();
        $this->project->addProjectBilling($billing);
        $this->project->addProjectBilling($billing);

        $this->assertCount(1, $this->project->getProjectBillings());
        $this->assertSame($this->project, $billing->getProject());
    }

    public function testRemoveProjectBillingClearsOwningSide(): void
    {
        $billing = new ProjectBilling();
        $this->project->addProjectBilling($billing);
        $this->project->removeProjectBilling($billing);

        $this->assertCount(0, $this->project->getProjectBillings());
        $this->assertNull($billing->getProject());
    }

    public function testAddIssueSetsOwningSide(): void
    {
        $issue = new Issue();
        $this->project->addIssue($issue);
        $this->project->addIssue($issue);

        $this->assertCount(1, $this->project->getIssues());
        $this->assertSame($this->project, $issue->getProject());
    }

    public function testRemoveIssueClearsOwningSide(): void
    {
        $issue = new Issue();
        $this->project->addIssue($issue);
        $this->project->removeIssue($issue);

        $this->assertCount(0, $this->project->getIssues());
        $this->assertNull($issue->getProject());
    }

    public function testAddProductSetsOwningSide(): void
    {
        $product = new Product();
        $this->project->addProduct($product);
        $this->project->addProduct($product);

        $this->assertCount(1, $this->project->getProducts());
        $this->assertSame($this->project, $product->getProject());
    }

    public function testRemoveProductClearsOwningSide(): void
    {
        $product = new Product();
        $this->project->addProduct($product);
        $this->project->removeProduct($product);

        $this->assertCount(0, $this->project->getProducts());
        $this->assertNull($product->getProject());
    }

    public function testCodeownersAreManagedAsAPlainCollection(): void
    {
        $worker = new Worker();

        $this->project->addCodeowner($worker);
        $this->project->addCodeowner($worker);
        $this->assertCount(1, $this->project->getCodeowners());

        $this->project->removeCodeowner($worker);
        $this->assertCount(0, $this->project->getCodeowners());
    }

    public function testRelationSettersAreFluent(): void
    {
        $this->assertSame($this->project, $this->project->setName('Economics'));
        $this->assertSame($this->project, $this->project->addInvoice(new Invoice()));
        $this->assertSame($this->project, $this->project->addClient(new Client()));
        $this->assertSame($this->project, $this->project->addVersion(new Version()));
        $this->assertSame($this->project, $this->project->addWorklog(new Worklog()));
        $this->assertSame($this->project, $this->project->addProjectBilling(new ProjectBilling()));
        $this->assertSame($this->project, $this->project->addIssue(new Issue()));
        $this->assertSame($this->project, $this->project->addProduct(new Product()));
        $this->assertSame($this->project, $this->project->addCodeowner(new Worker()));
    }
}
