<?php

namespace App\Tests\Integration\Controller;

use App\Entity\ProjectBilling;
use App\Enum\InvoiceEntryTypeEnum;
use App\Repository\ProjectBillingRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ProjectBillingFullFlowTest extends AbstractControllerTestCase
{
    public function testFullProjectBillingLifecycle(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_PROJECT_BILLING']);
        $container = static::getContainer();

        // Pick `project-0-2` rather than the default `project-0-0`: both share fixture
        // versions `PB-0-0`/`PB-0-1` (matching the data-provider-0 clients) and have
        // DONE-status issues with worklogs, but ProjectBillingServiceTest depends on
        // `project-0-0`'s worklogs staying unbilled, so we must not record against it.
        $project = $container->get(ProjectRepository::class)->findOneBy(['name' => 'project-0-2']);
        $this->assertNotNull($project, 'Expected fixture project `project-0-2`.');

        $periodStart = '2020-01-01';
        $periodEnd = '2099-12-31';
        $initialName = 'FullFlow-pb-'.uniqid();
        $initialDescription = 'Initial project billing description.';

        // 1. Create the project billing.
        $crawler = $client->request('GET', '/admin/project-billing/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="project_billing"]')->form();
        $form['project_billing[name]'] = $initialName;
        $form['project_billing[project]'] = (string) $project->getId();
        $form['project_billing[periodStart]'] = $periodStart;
        $form['project_billing[periodEnd]'] = $periodEnd;
        $form['project_billing[description]'] = $initialDescription;
        $client->submit($form);

        $this->assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        $this->assertMatchesRegularExpression('#/admin/project-billing/(\d+)/edit$#', $location);
        preg_match('#/admin/project-billing/(\d+)/edit$#', $location, $matches);
        $projectBillingId = (int) $matches[1];

        // The CreateProjectBillingMessage handler runs synchronously (no async routing in test env)
        // and should have generated invoices from the project's PB-* version issues.
        $projectBilling = $this->reload($projectBillingId);
        $this->assertInstanceOf(ProjectBilling::class, $projectBilling);
        $this->assertSame($initialName, $projectBilling->getName());
        $this->assertFalse((bool) $projectBilling->isRecorded());
        $this->assertSame($initialDescription, $projectBilling->getDescription());
        $this->assertSame($periodStart, $projectBilling->getPeriodStart()?->format('Y-m-d'));
        $this->assertSame($periodEnd, $projectBilling->getPeriodEnd()?->format('Y-m-d'));

        $generatedInvoices = $projectBilling->getInvoices();
        $this->assertGreaterThan(
            0,
            $generatedInvoices->count(),
            'Expected createProjectBilling to auto-generate at least one invoice from fixture issues.'
        );
        foreach ($generatedInvoices as $generated) {
            $this->assertNotNull($generated->getClient());
            $this->assertNotNull($generated->getDescription());
            $this->assertFalse((bool) $generated->isRecorded());
            $this->assertGreaterThan(0, $generated->getInvoiceEntries()->count(), 'Generated invoice should have entries.');
            foreach ($generated->getInvoiceEntries() as $entry) {
                $this->assertContains(
                    $entry->getEntryType(),
                    [InvoiceEntryTypeEnum::WORKLOG, InvoiceEntryTypeEnum::PRODUCT],
                );
                $this->assertGreaterThan(0, (float) $entry->getAmount(), 'Auto-generated entries must have amount > 0 to be recordable.');
            }
        }
        $initialInvoiceCount = $generatedInvoices->count();

        // 2. Edit the project billing — change description. UpdateProjectBillingMessage runs sync,
        // wipes non-recorded invoices, and regenerates them.
        $crawler = $client->request('GET', '/admin/project-billing/'.$projectBillingId.'/edit');
        $this->assertResponseIsSuccessful();

        $updatedName = 'FullFlow-pb-edited-'.uniqid();
        $updatedDescription = 'Updated project billing description.';
        $form = $crawler->filter('form[name="project_billing"]')->form();
        $form['project_billing[name]'] = $updatedName;
        $form['project_billing[description]'] = $updatedDescription;
        $client->submit($form);
        $this->assertResponseRedirects('/admin/project-billing/'.$projectBillingId.'/edit');

        $projectBilling = $this->reload($projectBillingId);
        $this->assertSame($updatedName, $projectBilling->getName());
        $this->assertSame($updatedDescription, $projectBilling->getDescription());
        $this->assertSame(
            $initialInvoiceCount,
            $projectBilling->getInvoices()->count(),
            'Update should regenerate the same set of invoices for unchanged fixture data.',
        );
        foreach ($projectBilling->getInvoices() as $invoice) {
            $this->assertSame($updatedDescription, $invoice->getDescription(), 'Regenerated invoices inherit the new description.');
        }

        // 3. Show-export preview (before record).
        $client->request('GET', '/admin/project-billing/'.$projectBillingId.'/show-export');
        $this->assertResponseIsSuccessful();

        // 4. Record. ProjectBillingRecordType is a GET-method form with a bool confirmation.
        $this->submitRecordForm($client, $projectBillingId, true);
        $this->assertResponseRedirects('/admin/project-billing/'.$projectBillingId.'/edit');

        $projectBilling = $this->reload($projectBillingId);
        $this->assertTrue((bool) $projectBilling->isRecorded());

        $invoiceIds = [];
        foreach ($projectBilling->getInvoices() as $invoice) {
            $this->assertTrue($invoice->isRecorded(), 'Child invoices should be recorded along with the project billing.');
            $this->assertNotNull($invoice->getRecordedDate());
            $this->assertContains($invoice->getLockedType(), ['INTERN', 'EKSTERN']);
            $this->assertNotEmpty($invoice->getLockedCustomerKey());
            $invoiceIds[] = $invoice->getId();
        }

        // 5. Export CSV.
        $client->request('GET', '/admin/project-billing/'.$projectBillingId.'/export');
        $this->assertResponseIsSuccessful();
        $response = $client->getResponse();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="invoices-', (string) $response->headers->get('Content-Disposition'));
        $body = (string) $response->getContent();
        $this->assertStringContainsString('H;', $body);
        $this->assertStringContainsString('L;', $body);

        $projectBilling = $this->reload($projectBillingId);
        $this->assertNotNull($projectBilling->getExportedDate(), 'Exporting should stamp exportedDate on the project billing.');
        foreach ($projectBilling->getInvoices() as $invoice) {
            $this->assertNotNull($invoice->getExportedDate(), 'Export should stamp exportedDate on each invoice.');
        }

        // 6. Filtered export — only internal invoices via the `?type=internal` query parameter.
        $client->request('GET', '/admin/project-billing/'.$projectBillingId.'/export?type=internal');
        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('text/csv', (string) $client->getResponse()->headers->get('Content-Type'));

        // 7. Show-export still works after record.
        $client->request('GET', '/admin/project-billing/'.$projectBillingId.'/show-export');
        $this->assertResponseIsSuccessful();
    }

    private function submitRecordForm(KernelBrowser $client, int $projectBillingId, bool $confirmation): void
    {
        $crawler = $client->request('GET', '/admin/project-billing/'.$projectBillingId.'/record');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="project_billing_record"]')->form();
        $form['project_billing_record[confirmation]'] = $confirmation ? '1' : '0';
        $client->submit($form);
    }

    private function reload(int $id): ?ProjectBilling
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $pb = static::getContainer()->get(ProjectBillingRepository::class)->find($id);
        if (null !== $pb) {
            $pb->getInvoices()->count();
            foreach ($pb->getInvoices() as $invoice) {
                $invoice->getInvoiceEntries()->count();
            }
        }

        return $pb;
    }
}
