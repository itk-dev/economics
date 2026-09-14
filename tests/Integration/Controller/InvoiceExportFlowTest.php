<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Project;
use App\Entity\ProjectBilling;
use App\Enum\InvoiceEntryTypeEnum;

/**
 * Covers the guards around deleting and exporting invoices — recorded invoices
 * and invoices belonging to a project billing must stay put.
 *
 * The 500s pin current behaviour, not desired: the guards throw uncaught.
 */
class InvoiceExportFlowTest extends AbstractTransactionalFlowTestCase
{
    private int $projectId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootTransactionalClient('ROLE_INVOICE');

        // Pinned by lead, which testGenerateDescriptionFillsInTheProjectLead asserts on.
        $project = $this->findOne(Project::class, [
            'projectLeadName' => 'Test Testesen',
            'projectLeadMail' => 'test@economics.local.itkdev.dk',
        ]);
        $this->projectId = $this->requireId($project->getId());
    }

    public function testDeleteRemovesAnOpenInvoice(): void
    {
        $id = $this->persistInvoice('Deletable invoice');

        $this->submitDeleteFormAt(sprintf('/admin/invoices/%d/edit', $id), '/admin/invoices/'.$id);

        $this->assertResponseRedirects('/admin/invoices/');
        $this->entityManager->clear();
        $this->assertNull($this->findByIdOrNull(Invoice::class, $id));
    }

    public function testDeleteWithAnInvalidTokenKeepsTheInvoice(): void
    {
        $id = $this->persistInvoice('Invoice kept by token check');

        $this->client->request('POST', '/admin/invoices/'.$id, ['_token' => 'invalid-token']);

        $this->assertResponseRedirects('/admin/invoices/');
        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(Invoice::class, $id));
    }

    public function testTheDeleteFormIsHiddenForRecordedInvoices(): void
    {
        $id = $this->persistInvoice('Recorded invoice', recorded: true);

        $crawler = $this->client->request('GET', sprintf('/admin/invoices/%d/edit', $id));

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter(sprintf('form[action$="/admin/invoices/%d"]', $id)));
    }

    public function testTheDeleteFormIsHiddenForInvoicesInAProjectBilling(): void
    {
        $id = $this->persistInvoice('Project billing invoice', projectBilling: true);

        $crawler = $this->client->request('GET', sprintf('/admin/invoices/%d/edit', $id));

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter(sprintf('form[action$="/admin/invoices/%d"]', $id)));
    }

    public function testAStaleDeleteFormCannotRemoveARecordedInvoice(): void
    {
        $id = $this->persistInvoice('Invoice recorded after render');
        $form = $this->deleteFormFor($id);

        $this->markRecorded($id);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(500);
        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(Invoice::class, $id));
    }

    public function testAStaleDeleteFormCannotRemoveAnInvoiceAddedToAProjectBilling(): void
    {
        $id = $this->persistInvoice('Invoice billed after render');
        $form = $this->deleteFormFor($id);

        $this->attachProjectBilling($id);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(500);
        $this->entityManager->clear();
        $this->assertNotNull($this->findByIdOrNull(Invoice::class, $id));
    }

    public function testExportRequiresTheInvoiceToBeRecorded(): void
    {
        $id = $this->persistInvoice('Unrecorded invoice');

        $this->client->request('GET', sprintf('/admin/invoices/%d/export', $id));

        $this->assertResponseStatusCodeSame(500);
    }

    public function testExportRejectsInvoicesInAProjectBilling(): void
    {
        $id = $this->persistInvoice('Recorded billing invoice', recorded: true, projectBilling: true);

        $this->client->request('GET', sprintf('/admin/invoices/%d/export', $id));

        $this->assertResponseStatusCodeSame(500);
    }

    public function testExportMarksTheInvoiceAsExported(): void
    {
        $id = $this->persistInvoice('Exportable invoice', recorded: true);

        $this->client->request('GET', sprintf('/admin/invoices/%d/export', $id));

        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $this->assertNotNull($this->findById(Invoice::class, $id)->getExportedDate());
    }

    public function testShowExportRendersForARecordedInvoice(): void
    {
        $id = $this->persistInvoice('Previewable invoice', recorded: true);

        $this->client->request('GET', sprintf('/admin/invoices/%d/show-export', $id));

        $this->assertResponseIsSuccessful();
    }

    public function testExportSelectionMarksEverySelectedInvoice(): void
    {
        $first = $this->persistInvoice('Selection invoice one', recorded: true);
        $second = $this->persistInvoice('Selection invoice two', recorded: true);

        $this->client->request('GET', sprintf('/admin/invoices/export-selection?ids=%d,%d', $first, $second));

        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $this->assertNotNull($this->findById(Invoice::class, $first)->getExportedDate());
        $this->assertNotNull($this->findById(Invoice::class, $second)->getExportedDate());
    }

    public function testExportSelectionSkipsUnknownIds(): void
    {
        $id = $this->persistInvoice('Selection invoice', recorded: true);

        $this->client->request('GET', sprintf('/admin/invoices/export-selection?ids=%d,99999999', $id));

        $this->assertResponseIsSuccessful();
    }

    public function testExportSelectionWithoutIdsIsAccepted(): void
    {
        $this->client->request('GET', '/admin/invoices/export-selection');

        $this->assertResponseIsSuccessful();
    }

    public function testExportSelectionRejectsAnUnrecordedInvoice(): void
    {
        $id = $this->persistInvoice('Unrecorded selection invoice');

        $this->client->request('GET', sprintf('/admin/invoices/export-selection?ids=%d', $id));

        $this->assertResponseStatusCodeSame(500);
    }

    public function testExportSelectionRejectsAProjectBillingInvoice(): void
    {
        $id = $this->persistInvoice('Billing selection invoice', recorded: true, projectBilling: true);

        $this->client->request('GET', sprintf('/admin/invoices/export-selection?ids=%d', $id));

        $this->assertResponseStatusCodeSame(500);
    }

    public function testGenerateDescriptionFillsInTheProjectLead(): void
    {
        $id = $this->persistInvoice('Described invoice');

        $this->client->request('GET', sprintf('/admin/invoices/%d/generate-description', $id));

        $this->assertResponseIsSuccessful();

        $description = $this->responseJson()['description'];
        $this->assertIsString($description);
        $this->assertStringContainsString('Test Testesen', $description);
        $this->assertStringContainsString('test@economics.local.itkdev.dk', $description);
    }

    public function testGenerateDescriptionIsEmptyWithoutAProjectLead(): void
    {
        $projectId = $this->persistProjectWithoutLead();
        $id = $this->persistInvoice('Leadless invoice', projectId: $projectId);

        $this->client->request('GET', sprintf('/admin/invoices/%d/generate-description', $id));

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->responseJson()['description']);
    }

    public function testEditKeepsAnUnsyncedReceiverAccountSelectable(): void
    {
        $id = $this->persistInvoice('Legacy account invoice', legacyAccounts: true);

        $crawler = $this->client->request('GET', sprintf('/admin/invoices/%d/edit', $id));

        $this->assertResponseIsSuccessful();

        $options = $crawler->filter('select[name="invoice[defaultReceiverAccount]"] option')
            ->each(fn ($node) => $node->attr('value'));
        $this->assertContains('legacy-receiver', $options);

        $paidBy = $crawler->filter('select[name="invoice[paidByAccount]"] option')
            ->each(fn ($node) => $node->attr('value'));
        $this->assertContains('legacy-paid-by', $paidBy);
    }

    private function deleteFormFor(int $invoiceId): \Symfony\Component\DomCrawler\Form
    {
        $crawler = $this->client->request('GET', sprintf('/admin/invoices/%d/edit', $invoiceId));
        $this->assertResponseIsSuccessful();

        return $crawler->filter(sprintf('form[action$="/admin/invoices/%d"]', $invoiceId))->form();
    }

    private function markRecorded(int $invoiceId): void
    {
        $invoice = $this->findById(Invoice::class, $invoiceId);
        $invoice->setRecorded(true);
        $invoice->setRecordedDate(new \DateTime('2026-03-01'));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function attachProjectBilling(int $invoiceId): void
    {
        $invoice = $this->findById(Invoice::class, $invoiceId);

        $billing = new ProjectBilling();
        $billing->setName('Late billing');
        $billing->setProject($this->findById(Project::class, $this->projectId));
        $billing->setRecorded(false);
        $billing->setPeriodStart(new \DateTime('2026-01-01'));
        $billing->setPeriodEnd(new \DateTime('2026-12-31'));
        $this->entityManager->persist($billing);

        $invoice->setProjectBilling($billing);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function persistInvoice(
        string $name,
        bool $recorded = false,
        bool $projectBilling = false,
        ?int $projectId = null,
        bool $legacyAccounts = false,
    ): int {
        $project = $this->findById(Project::class, $projectId ?? $this->projectId);

        $invoice = new Invoice();
        $invoice->setName($name);
        $invoice->setProject($project);
        $invoice->setClient($this->findOne(Client::class));
        $invoice->setRecorded($recorded);
        $invoice->setTotalPrice(100.0);

        if ($recorded) {
            $invoice->setRecordedDate(new \DateTime('2026-03-01'));
        }

        if ($legacyAccounts) {
            $invoice->setDefaultReceiverAccount('legacy-receiver');
            $invoice->setPaidByAccount('legacy-paid-by');
        }

        if ($projectBilling) {
            $billing = new ProjectBilling();
            $billing->setName('Billing for '.$name);
            $billing->setProject($project);
            $billing->setRecorded(false);
            $billing->setPeriodStart(new \DateTime('2026-01-01'));
            $billing->setPeriodEnd(new \DateTime('2026-12-31'));
            $this->entityManager->persist($billing);
            $invoice->setProjectBilling($billing);
        }

        $this->entityManager->persist($invoice);

        $entry = new InvoiceEntry();
        $entry->setInvoice($invoice);
        $entry->setEntryType(InvoiceEntryTypeEnum::MANUAL);
        $entry->setIndex(0);
        $entry->setProduct('Line item');
        $entry->setPrice(100.0);
        $entry->setAmount(1.0);
        $entry->setTotalPrice(100.0);
        $this->entityManager->persist($entry);

        $this->entityManager->flush();
        $id = $this->requireId($invoice->getId());
        $this->entityManager->clear();

        return $id;
    }

    private function persistProjectWithoutLead(): int
    {
        $project = new Project();
        $project->setName('Project without lead');
        $project->setProjectTrackerId('no-lead-'.uniqid());
        $project->setProjectTrackerKey('NLP');
        $project->setProjectTrackerProjectUrl('http://localhost/');
        $project->setInclude(true);
        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $id = $this->requireId($project->getId());
        $this->entityManager->clear();

        return $id;
    }
}
