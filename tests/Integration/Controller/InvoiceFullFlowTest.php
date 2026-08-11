<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Worklog;
use App\Enum\ClientTypeEnum;
use App\Enum\InvoiceEntryTypeEnum;
use App\Enum\MaterialNumberEnum;
use App\Model\Invoices\ConfirmData;
use App\Repository\ClientRepository;
use App\Repository\InvoiceEntryRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use App\Repository\WorklogRepository;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceFullFlowTest extends AbstractControllerTestCase
{
    public function testFullInvoiceLifecycle(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        $container = static::getContainer();

        $project = $container->get(ProjectRepository::class)->getIncluded()
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $this->assertNotNull($project, 'Expected an included project from fixtures.');

        $internalClient = $container->get(ClientRepository::class)
            ->findOneBy(['type' => ClientTypeEnum::INTERNAL]);
        $this->assertInstanceOf(Client::class, $internalClient, 'Expected an internal client fixture.');

        $externalClient = $container->get(ClientRepository::class)
            ->findOneBy(['type' => ClientTypeEnum::EXTERNAL]);
        $this->assertInstanceOf(Client::class, $externalClient, 'Expected an external client fixture.');

        // 1. Create invoice.
        $crawler = $client->request('GET', '/admin/invoices/new');
        $this->assertResponseIsSuccessful();

        $initialName = 'FullFlow-create-'.uniqid();
        $form = $crawler->filter('form[name="invoice_new"]')->form();
        $form['invoice_new[name]'] = $initialName;
        $form['invoice_new[project]'] = (string) $project->getId();
        $client->submit($form);

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertMatchesRegularExpression('#/admin/invoices/(\d+)/edit$#', $location, 'Expected redirect to invoice edit.');
        preg_match('#/admin/invoices/(\d+)/edit$#', $location, $matches);
        $invoiceId = (int) $matches[1];

        // 2. Edit invoice and set all fields.
        $crawler = $client->request('GET', '/admin/invoices/'.$invoiceId.'/edit');
        $this->assertResponseIsSuccessful();

        // The client select autofills the material number via a Stimulus controller.
        $editHtml = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('data-autofill-material-number-map-value', $editHtml);
        $this->assertStringContainsString('data-autofill-material-number-target="client"', $editHtml);
        $this->assertStringContainsString('data-autofill-material-number-target="material"', $editHtml);
        $this->assertStringContainsString('autofill-material-number#update', $editHtml);

        // The same client select also autoselects the receiver account.
        $this->assertStringContainsString('data-autofill-receiver-account-map-value', $editHtml);
        $this->assertStringContainsString('data-autofill-receiver-account-target="client"', $editHtml);
        $this->assertStringContainsString('data-autofill-receiver-account-target="receiver"', $editHtml);
        $this->assertStringContainsString('autofill-receiver-account#update', $editHtml);

        // External clients map to the configured external receiver account, while
        // internal clients map to the default account.
        $receiverMapJson = $crawler->filter('[data-autofill-receiver-account-map-value]')
            ->attr('data-autofill-receiver-account-map-value');
        $this->assertNotNull($receiverMapJson);
        $receiverMap = json_decode($receiverMapJson, true);
        $this->assertSame('ACC002', $receiverMap[$externalClient->getId()] ?? null);
        $this->assertSame('test', $receiverMap[$internalClient->getId()] ?? null);

        $finalName = 'FullFlow-edit-'.uniqid();
        $description = 'Full flow invoice description.';
        $periodFrom = '2025-01-01';
        $periodTo = '2025-01-31';

        $form = $crawler->filter('form[name="invoice"]')->form();
        $form['invoice[name]'] = $finalName;
        $form['invoice[description]'] = $description;
        $form['invoice[client]'] = (string) $internalClient->getId();
        $form['invoice[paidByAccount]'] = 'ACC001';
        $form['invoice[defaultReceiverAccount]'] = 'ACC002';
        $form['invoice[defaultMaterialNumber]'] = MaterialNumberEnum::INTERNAL->value;
        $form['invoice[periodFrom]'] = $periodFrom;
        $form['invoice[periodTo]'] = $periodTo;
        $client->submit($form);

        $this->assertResponseIsSuccessful();

        $invoice = $this->reloadInvoice($invoiceId);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame($finalName, $invoice->getName());
        $this->assertSame($description, $invoice->getDescription());
        $this->assertNotNull($invoice->getClient());
        $this->assertSame($internalClient->getId(), $invoice->getClient()->getId());
        $this->assertSame('ACC001', $invoice->getPaidByAccount());
        $this->assertSame('ACC002', $invoice->getDefaultReceiverAccount());
        $this->assertSame(MaterialNumberEnum::INTERNAL, $invoice->getDefaultMaterialNumber());
        $this->assertSame($periodFrom, $invoice->getPeriodFrom()?->format('Y-m-d'));
        $this->assertSame($periodTo, $invoice->getPeriodTo()?->format('Y-m-d'));

        // 3. Add a MANUAL entry.
        $manualProduct = 'Manual service line';
        $manualEntryId = $this->submitNewEntry(
            $client,
            $invoiceId,
            InvoiceEntryTypeEnum::MANUAL,
            'invoice_entry',
            [
                'product' => $manualProduct,
                'price' => '750',
                'amount' => '2.5',
            ],
            expectedRedirectPattern: '#/admin/invoices/'.$invoiceId.'/edit$#',
        );

        $manualEntry = $this->reloadInvoiceEntry($manualEntryId);
        $this->assertInstanceOf(InvoiceEntry::class, $manualEntry);
        $this->assertSame(InvoiceEntryTypeEnum::MANUAL, $manualEntry->getEntryType());
        $this->assertSame($manualProduct, $manualEntry->getProduct());
        $this->assertEqualsWithDelta(750.0, $manualEntry->getPrice(), 0.001);
        $this->assertEqualsWithDelta(2.5, $manualEntry->getAmount(), 0.001);
        $this->assertEqualsWithDelta(1875.0, $manualEntry->getTotalPrice(), 0.001);

        // 4. Add a PRODUCT entry.
        $productProduct = 'Product line item';
        $productEntryId = $this->submitNewEntry(
            $client,
            $invoiceId,
            InvoiceEntryTypeEnum::PRODUCT,
            'invoice_entry',
            [
                'product' => $productProduct,
                'price' => '300',
                'amount' => '4',
            ],
            expectedRedirectPattern: '#/admin/invoices/'.$invoiceId.'/entries/\d+/edit$#',
        );

        $productEntry = $this->reloadInvoiceEntry($productEntryId);
        $this->assertInstanceOf(InvoiceEntry::class, $productEntry);
        $this->assertSame(InvoiceEntryTypeEnum::PRODUCT, $productEntry->getEntryType());
        $this->assertEqualsWithDelta(1200.0, $productEntry->getTotalPrice(), 0.001);

        // 5. Add a WORKLOG entry.
        $worklogProduct = 'Hourly work';
        $worklogEntryId = $this->submitNewEntry(
            $client,
            $invoiceId,
            InvoiceEntryTypeEnum::WORKLOG,
            'invoice_entry_worklog',
            [
                'product' => $worklogProduct,
                'price' => '500',
            ],
            expectedRedirectPattern: '#/admin/invoices/'.$invoiceId.'/entries/\d+/edit$#',
        );

        $worklogEntry = $this->reloadInvoiceEntry($worklogEntryId);
        $this->assertInstanceOf(InvoiceEntry::class, $worklogEntry);
        $this->assertSame(InvoiceEntryTypeEnum::WORKLOG, $worklogEntry->getEntryType());
        $this->assertSame(0.0, (float) $worklogEntry->getAmount());

        // 6. Attach worklogs to the WORKLOG entry.
        $worklogsCrawler = $client->request('GET', '/admin/invoices/'.$invoiceId.'/entries/'.$worklogEntryId.'/worklogs');
        $this->assertResponseIsSuccessful();

        // The sums bar reports the total hours of the filtered list, and each
        // checkbox carries the time the Stimulus controller sums for the selection.
        $sums = $worklogsCrawler->filter('.sticky-actions-sums');
        $this->assertCount(1, $sums);
        $this->assertSame('0', trim($sums->filter('[data-entry-select-target="selectedHours"]')->text()));
        $this->assertGreaterThan(0, (float) trim($sums->filter('span.font-bold')->last()->text()));
        $this->assertGreaterThan(0, $worklogsCrawler->filter('input[data-time-spent-seconds]')->count());

        $worklogRepository = static::getContainer()->get(WorklogRepository::class);
        $unbilled = $worklogRepository->findBy(
            ['project' => $project, 'isBilled' => false],
            ['id' => 'ASC'],
            50
        );
        $selected = [];
        foreach ($unbilled as $wl) {
            if (null === $wl->getInvoiceEntry()) {
                $selected[] = $wl;
                if (3 === count($selected)) {
                    break;
                }
            }
        }
        $this->assertCount(3, $selected, 'Expected 3 unbilled, unassigned worklogs in fixtures.');

        $expectedSeconds = 0;
        $payload = [];
        foreach ($selected as $wl) {
            $payload[] = ['id' => $wl->getId(), 'checked' => true];
            $expectedSeconds += (int) $wl->getTimeSpentSeconds();
        }

        $client->request(
            'POST',
            '/admin/invoices/'.$invoiceId.'/entries/'.$worklogEntryId.'/select_worklogs',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload),
        );
        $this->assertResponseIsSuccessful();

        $worklogEntry = $this->reloadInvoiceEntry($worklogEntryId);
        $expectedHours = $expectedSeconds / 3600;
        $this->assertEqualsWithDelta($expectedHours, $worklogEntry->getAmount(), 0.0001);
        $this->assertEqualsWithDelta(500 * $expectedHours, $worklogEntry->getTotalPrice(), 0.0001);

        // 7. HTML export preview before record.
        $client->request('GET', '/admin/invoices/'.$invoiceId.'/show-export');
        $this->assertResponseIsSuccessful();
        $preRecordExport = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString($manualProduct, $preRecordExport);
        $this->assertStringContainsString($worklogProduct, $preRecordExport);

        // 8. Record the invoice.
        $crawler = $client->request('GET', '/admin/invoices/'.$invoiceId.'/record');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="invoice_record"]')->form();
        $form['invoice_record[confirmation]'] = ConfirmData::INVOICE_RECORD_YES;
        $client->submit($form);
        $this->assertResponseRedirects('/admin/invoices/'.$invoiceId.'/edit');

        $invoice = $this->reloadInvoice($invoiceId);
        $this->assertTrue($invoice->isRecorded());
        $this->assertNotNull($invoice->getRecordedDate());
        $this->assertSame('INTERN', $invoice->getLockedType());
        $this->assertSame($internalClient->getCustomerKey(), $invoice->getLockedCustomerKey());
        $this->assertSame($internalClient->getContact(), $invoice->getLockedContactName());
        $this->assertSame($internalClient->getEan() ?? '', $invoice->getLockedEan());
        $this->assertFalse($invoice->isNoCost());

        // Worklogs attached to the WORKLOG entry should be marked billed.
        $selectedIds = array_map(static fn (Worklog $wl) => $wl->getId(), $selected);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $worklogRepository = static::getContainer()->get(WorklogRepository::class);
        foreach ($selectedIds as $wlId) {
            $wl = $worklogRepository->find($wlId);
            $this->assertNotNull($wl);
            $this->assertTrue($wl->isBilled(), 'Expected worklog to be marked as billed after record.');
            $this->assertSame($wl->getTimeSpentSeconds(), $wl->getBilledSeconds());
        }

        // 9. CSV export.
        $client->request('GET', '/admin/invoices/'.$invoiceId.'/export');
        $this->assertResponseIsSuccessful();
        $response = $client->getResponse();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="invoices-', (string) $response->headers->get('Content-Disposition'));
        $body = (string) $response->getContent();
        $this->assertNotSame('', $body);
        $this->assertStringContainsString('H;', $body, 'Expected CSV to contain header (H;) row.');
        $this->assertStringContainsString('L;', $body, 'Expected CSV to contain at least one line (L;) row.');

        $invoice = $this->reloadInvoice($invoiceId);
        $this->assertNotNull($invoice->getExportedDate(), 'Expected exportedDate to be set after CSV export.');

        // 10. HTML export preview still works after record.
        $client->request('GET', '/admin/invoices/'.$invoiceId.'/show-export');
        $this->assertResponseIsSuccessful();
    }

    /**
     * @param array<string, string> $fields
     */
    private function submitNewEntry(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        int $invoiceId,
        InvoiceEntryTypeEnum $type,
        string $formName,
        array $fields,
        string $expectedRedirectPattern,
    ): int {
        $crawler = $client->request('GET', '/admin/invoices/'.$invoiceId.'/entries/new/'.$type->value);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="'.$formName.'"]')->form();
        foreach ($fields as $key => $value) {
            $form[$formName.'['.$key.']'] = $value;
        }
        $client->submit($form);

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertMatchesRegularExpression($expectedRedirectPattern, $location);

        if (str_contains($expectedRedirectPattern, '/entries/\d+/edit')) {
            preg_match('#/entries/(\d+)/edit$#', $location, $matches);

            return (int) $matches[1];
        }

        // MANUAL redirects back to invoice edit — fetch the most recently created entry for this invoice.
        $repository = static::getContainer()->get(InvoiceEntryRepository::class);
        $latest = $repository->findBy([], ['id' => 'DESC'], 1);
        $this->assertNotEmpty($latest);

        return $latest[0]->getId();
    }

    private function reloadInvoice(int $id): ?Invoice
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return static::getContainer()->get(InvoiceRepository::class)->find($id);
    }

    private function reloadInvoiceEntry(int $id): ?InvoiceEntry
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return static::getContainer()->get(InvoiceEntryRepository::class)->find($id);
    }
}
