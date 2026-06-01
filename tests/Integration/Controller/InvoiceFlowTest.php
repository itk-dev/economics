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

        $projectRepository = static::getContainer()->get(ProjectRepository::class);
        \assert($projectRepository instanceof ProjectRepository);
        $project = $projectRepository->getIncluded()
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $this->assertNotNull($project, 'Expected an included project from fixtures.');

        $name = 'Smoke-invoice-'.uniqid();
        $form = $crawler->filter('form[name="invoice_new"]')->form();
        $form['invoice_new[name]'] = $name;
        $form['invoice_new[project]'] = (string) $project->getId();
        $client->submit($form);

        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertMatchesRegularExpression('#/admin/invoices/\d+/edit$#', $location);

        $invoiceRepository = static::getContainer()->get(InvoiceRepository::class);
        \assert($invoiceRepository instanceof InvoiceRepository);
        $created = $invoiceRepository->findOneBy(['name' => $name]);
        $this->assertInstanceOf(Invoice::class, $created);
        $createdProject = $created->getProject();
        $this->assertNotNull($createdProject);
        $this->assertSame($project->getId(), $createdProject->getId());
        $this->assertFalse($created->isRecorded());
    }

    public function testEditExistingInvoiceRendersForm(): void
    {
        $invoiceRepository = static::getContainer()->get(InvoiceRepository::class);
        \assert($invoiceRepository instanceof InvoiceRepository);
        $invoice = $invoiceRepository->findOneBy([]);
        $this->assertNotNull($invoice, 'Expected at least one invoice from fixtures.');

        $client = $this->createClientLoggedInAs(['ROLE_INVOICE']);
        $crawler = $client->request('GET', '/admin/invoices/'.$invoice->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('form[name="invoice"]')->count(), 'Expected an invoice edit form on the page.');
    }
}
