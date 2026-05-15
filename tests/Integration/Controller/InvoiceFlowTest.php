<?php

namespace App\Tests\Integration\Controller;

use App\Entity\Invoice;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;

class InvoiceFlowTest extends AbstractControllerTestCase
{
    public function testCreateInvoicePersistsAndRedirectsToEdit(): void
    {
        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        $crawler = $client->request('GET', '/admin/invoices/new');
        $this->assertResponseIsSuccessful();

        $project = static::getContainer()->get(ProjectRepository::class)->getIncluded()
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $this->assertNotNull($project, 'Expected an included project from fixtures.');

        $name = 'Smoke-invoice-'.uniqid();
        $form = $crawler->filter('form[name="invoice_new"]')->form();
        $form['invoice_new[name]'] = $name;
        $form['invoice_new[project]'] = (string) $project->getId();
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/admin/invoices/\d+/edit$#', $client->getResponse()->headers->get('Location'));

        $created = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['name' => $name]);
        $this->assertInstanceOf(Invoice::class, $created);
        $this->assertSame($project->getId(), $created->getProject()->getId());
        $this->assertFalse($created->isRecorded());
    }

    public function testEditExistingInvoiceRendersForm(): void
    {
        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy([]);
        $this->assertNotNull($invoice, 'Expected at least one invoice from fixtures.');

        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        $crawler = $client->request('GET', '/admin/invoices/'.$invoice->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('form[name="invoice"]')->count(), 'Expected an invoice edit form on the page.');
    }
}
