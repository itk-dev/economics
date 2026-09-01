<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Account;
use App\Entity\CybersecurityAgreement;
use App\Entity\DataProvider;
use App\Entity\Epic;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\IssueProduct;
use App\Entity\Product;
use App\Entity\Project;
use App\Entity\ProjectBilling;
use App\Entity\ProjectVersionBudget;
use App\Entity\ServiceAgreement;
use App\Entity\User;
use App\Entity\Worker;
use App\Entity\WorkerGroup;
use PHPUnit\Framework\TestCase;

/**
 * Accessor and relation coverage for the entities that are too small to warrant
 * a file of their own.
 */
class SmallEntitiesTest extends TestCase
{
    public function testAccountAccessors(): void
    {
        $account = new Account();
        $dataProvider = new DataProvider();

        $account->setName('Test Account');
        $account->setValue('12345');
        $account->setProjectTrackerId('acc-1');
        $account->setDataProvider($dataProvider);

        $this->assertSame('Test Account', $account->getName());
        $this->assertSame('12345', $account->getValue());
        $this->assertSame('acc-1', $account->getProjectTrackerId());
        $this->assertSame($dataProvider, $account->getDataProvider());
        $this->assertNull($account->getId());
    }

    public function testAccountToStringCombinesValueAndName(): void
    {
        $account = new Account();
        $account->setName('Test Account');
        $account->setValue('12345');

        $this->assertSame('12345: Test Account', (string) $account);
    }

    public function testAccountSettersAreFluent(): void
    {
        $account = new Account();

        $this->assertSame($account, $account->setName('A'));
        $this->assertSame($account, $account->setValue('1'));
        $this->assertSame($account, $account->setProjectTrackerId(null));
    }

    public function testCybersecurityAgreementAccessors(): void
    {
        $agreement = new CybersecurityAgreement();
        $serviceAgreement = new ServiceAgreement();

        $agreement->setServiceAgreement($serviceAgreement);
        $agreement->setQuarterlyHours(12.5);
        $agreement->setPrice(4500.0);
        $agreement->setNote('Quarterly review');

        $this->assertNull($agreement->getId());
        $this->assertSame($serviceAgreement, $agreement->getServiceAgreement());
        $this->assertSame(12.5, $agreement->getQuarterlyHours());
        $this->assertSame(4500.0, $agreement->getPrice());
        $this->assertSame('Quarterly review', $agreement->getNote());
    }

    public function testCybersecurityAgreementNullableAccessors(): void
    {
        $agreement = new CybersecurityAgreement();

        $agreement->setServiceAgreement(null);
        $agreement->setPrice(null);
        $agreement->setNote(null);

        $this->assertNull($agreement->getServiceAgreement());
        $this->assertNull($agreement->getPrice());
        $this->assertNull($agreement->getNote());
    }

    public function testDataProviderAccessors(): void
    {
        $dataProvider = new DataProvider();

        $dataProvider->setName('Leantime 1');
        $dataProvider->setUrl('https://leantime.example');
        $dataProvider->setSecret('s3cret');
        $dataProvider->setClass('App\Service\LeantimeApiService');
        $dataProvider->setEnableClientSync(true);
        $dataProvider->setEnableAccountSync(false);
        $dataProvider->setEnabled(true);

        $this->assertSame('Leantime 1', $dataProvider->getName());
        $this->assertSame('https://leantime.example', $dataProvider->getUrl());
        $this->assertSame('s3cret', $dataProvider->getSecret());
        $this->assertSame('App\Service\LeantimeApiService', $dataProvider->getClass());
        $this->assertTrue($dataProvider->isEnableClientSync());
        $this->assertFalse($dataProvider->isEnableAccountSync());
        $this->assertTrue($dataProvider->isEnabled());
    }

    public function testDataProviderNullableAccessors(): void
    {
        $dataProvider = new DataProvider();

        $dataProvider->setUrl(null);
        $dataProvider->setSecret(null);
        $dataProvider->setEnabled(null);

        $this->assertNull($dataProvider->getUrl());
        $this->assertNull($dataProvider->getSecret());
        $this->assertNull($dataProvider->isEnabled());
    }

    public function testDataProviderToString(): void
    {
        $dataProvider = new DataProvider();
        $this->assertSame('', (string) $dataProvider);

        $dataProvider->setName('Leantime 1');
        $this->assertSame('Leantime 1', (string) $dataProvider);
    }

    public function testEpicAccessorsAndIssueCollection(): void
    {
        $epic = new Epic();
        $issue = new Issue();

        $this->assertNull($epic->getId());
        $this->assertCount(0, $epic->getIssues());

        $epic->setTitle('Epic One');
        $this->assertSame('Epic One', $epic->getTitle());

        $epic->addIssue($issue);
        $epic->addIssue($issue);
        $this->assertCount(1, $epic->getIssues());

        $epic->removeIssue($issue);
        $this->assertCount(0, $epic->getIssues());
    }

    public function testIssueProductAccessors(): void
    {
        $issueProduct = new IssueProduct();
        $issue = new Issue();
        $product = new Product();
        $invoiceEntry = new InvoiceEntry();

        $issueProduct->setIssue($issue);
        $issueProduct->setProduct($product);
        $issueProduct->setQuantity(3.0);
        $issueProduct->setDescription('Three units');
        $issueProduct->setInvoiceEntry($invoiceEntry);
        $issueProduct->setIsBilled(true);

        $this->assertSame($issue, $issueProduct->getIssue());
        $this->assertSame($product, $issueProduct->getProduct());
        $this->assertSame(3.0, $issueProduct->getQuantity());
        $this->assertSame('Three units', $issueProduct->getDescription());
        $this->assertSame($invoiceEntry, $issueProduct->getInvoiceEntry());
        $this->assertTrue($issueProduct->isBilled());
    }

    public function testIssueProductTotalMultipliesPriceByQuantity(): void
    {
        $product = new Product();
        $product->setPrice('99.50');

        $issueProduct = new IssueProduct();
        $issueProduct->setProduct($product);
        $issueProduct->setQuantity(4.0);

        $this->assertSame(398.0, $issueProduct->getTotal());
    }

    public function testIssueProductTotalIsZeroWithoutAProduct(): void
    {
        $issueProduct = new IssueProduct();
        $issueProduct->setProduct(null);
        $issueProduct->setQuantity(4.0);

        $this->assertSame(0.0, $issueProduct->getTotal());
    }

    public function testProductAccessors(): void
    {
        $product = new Product();
        $project = new Project();

        $this->assertCount(0, $product->getIssues());

        $product->setName('Product Alpha');
        $product->setProject($project);
        $product->setPrice('199.95');

        $this->assertSame('Product Alpha', $product->getName());
        $this->assertSame($project, $product->getProject());
        $this->assertSame('199.95', $product->getPrice());
        $this->assertSame(199.95, $product->getPriceAsFloat());
    }

    public function testProductIssueCollectionKeepsBothSidesInSync(): void
    {
        $product = new Product();
        $issueProduct = new IssueProduct();

        $product->addIssue($issueProduct);
        $product->addIssue($issueProduct);
        $this->assertCount(1, $product->getIssues());
        $this->assertSame($product, $issueProduct->getProduct());

        $product->removeIssue($issueProduct);
        $this->assertCount(0, $product->getIssues());
        $this->assertNull($issueProduct->getProduct());
    }

    public function testProductRemoveIssueLeavesForeignOwnerAlone(): void
    {
        $owner = new Product();
        $other = new Product();
        $issueProduct = new IssueProduct();

        $owner->addIssue($issueProduct);
        $other->getIssues()->add($issueProduct);
        $other->removeIssue($issueProduct);

        $this->assertSame($owner, $issueProduct->getProduct());
    }

    public function testProjectBillingAccessors(): void
    {
        $billing = new ProjectBilling();
        $project = new Project();
        $periodStart = new \DateTime('2026-01-01');
        $periodEnd = new \DateTime('2026-03-31');
        $exportedDate = new \DateTime('2026-04-01');

        $this->assertCount(0, $billing->getInvoices());

        $billing->setName('Billing Q1');
        $billing->setPeriodStart($periodStart);
        $billing->setPeriodEnd($periodEnd);
        $billing->setProject($project);
        $billing->setRecorded(true);
        $billing->setDescription('First quarter');
        $billing->setExportedDate($exportedDate);

        $this->assertSame('Billing Q1', $billing->getName());
        $this->assertSame($periodStart, $billing->getPeriodStart());
        $this->assertSame($periodEnd, $billing->getPeriodEnd());
        $this->assertSame($project, $billing->getProject());
        $this->assertTrue($billing->isRecorded());
        $this->assertSame('First quarter', $billing->getDescription());
        $this->assertSame($exportedDate, $billing->getExportedDate());
    }

    public function testProjectBillingInvoiceCollectionKeepsBothSidesInSync(): void
    {
        $billing = new ProjectBilling();
        $invoice = new Invoice();

        $billing->addInvoice($invoice);
        $billing->addInvoice($invoice);
        $this->assertCount(1, $billing->getInvoices());
        $this->assertSame($billing, $invoice->getProjectBilling());

        $billing->removeInvoice($invoice);
        $this->assertCount(0, $billing->getInvoices());
        $this->assertNull($invoice->getProjectBilling());
    }

    public function testProjectBillingRemoveInvoiceLeavesForeignOwnerAlone(): void
    {
        $owner = new ProjectBilling();
        $other = new ProjectBilling();
        $invoice = new Invoice();

        $owner->addInvoice($invoice);
        $other->getInvoices()->add($invoice);
        $other->removeInvoice($invoice);

        $this->assertSame($owner, $invoice->getProjectBilling());
    }

    public function testProjectVersionBudgetAccessors(): void
    {
        $budget = new ProjectVersionBudget();

        $budget->setProjectId('project-1');
        $budget->setVersionId('version-1');
        $budget->setBudget(50000.0);

        $this->assertSame('project-1', $budget->getProjectId());
        $this->assertSame('version-1', $budget->getVersionId());
        $this->assertSame(50000.0, $budget->getBudget());
        $this->assertNull($budget->getId());
    }

    public function testUserAccessors(): void
    {
        $user = new User();

        $user->setEmail('user@test.local');
        $user->setName('Test User');
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $this->assertNull($user->getId());
        $this->assertSame('user@test.local', $user->getEmail());
        $this->assertSame('Test User', $user->getName());
        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
        $this->assertSame('user@test.local', $user->getUserIdentifier());
    }

    public function testUserRolesAreDeduplicated(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN']);

        $this->assertSame(['ROLE_USER', 'ROLE_ADMIN'], array_values($user->getRoles()));
    }

    public function testEraseCredentialsIsANoop(): void
    {
        $user = new User();
        $user->setEmail('user@test.local');

        $user->eraseCredentials();

        $this->assertSame('user@test.local', $user->getEmail());
    }

    public function testWorkerAccessors(): void
    {
        $worker = new Worker();

        $this->assertNull($worker->getId());
        $this->assertTrue($worker->getIncludeInReports());
        $this->assertCount(0, $worker->getWorkerGroups());

        $worker->setEmail('worker@test.local');
        $worker->setName('Test Worker');
        $worker->setWorkload(37.0);
        $worker->setIncludeInReports(false);

        $this->assertSame('worker@test.local', $worker->getEmail());
        $this->assertSame('Test Worker', $worker->getName());
        $this->assertSame(37.0, $worker->getWorkload());
        $this->assertFalse($worker->getIncludeInReports());
        $this->assertSame('worker@test.local', $worker->getUserIdentifier());
    }

    public function testWorkerToStringPrefersNameThenEmail(): void
    {
        $worker = new Worker();
        $this->assertSame('', (string) $worker);

        $worker->setEmail('worker@test.local');
        $this->assertSame('worker@test.local', (string) $worker);

        $worker->setName('Test Worker');
        $this->assertSame('Test Worker', (string) $worker);
    }

    public function testWorkerGroupMembershipKeepsBothSidesInSync(): void
    {
        $worker = new Worker();
        $group = new WorkerGroup();

        $worker->addWorkerGroup($group);
        $worker->addWorkerGroup($group);
        $this->assertCount(1, $worker->getWorkerGroups());
        $this->assertTrue($group->getWorkers()->contains($worker));

        $worker->removeWorkerGroup($group);
        $this->assertCount(0, $worker->getWorkerGroups());
        $this->assertCount(0, $group->getWorkers());
    }

    public function testWorkerGroupAccessors(): void
    {
        $group = new WorkerGroup();
        $worker = new Worker();

        $this->assertNull($group->getId());
        $this->assertCount(0, $group->getWorkers());

        $group->setName('Group Alpha');
        $this->assertSame('Group Alpha', $group->getName());
        $this->assertSame('Group Alpha', (string) $group);

        $group->addWorker($worker);
        $group->addWorker($worker);
        $this->assertCount(1, $group->getWorkers());

        $group->removeWorker($worker);
        $this->assertCount(0, $group->getWorkers());
    }
}
