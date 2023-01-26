<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Enum\InvoiceEntryTypeEnum;
use App\Form\InvoiceEntryType;
use App\Form\InvoiceEntryWorklogType;
use App\Repository\InvoiceEntryRepository;
use App\Service\BillingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/invoices/{invoice}/entries')]
class InvoiceEntryController extends AbstractController
{
    #[Route('/new/{type}', name: 'app_invoice_entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Invoice $invoice, InvoiceEntryTypeEnum $type, InvoiceEntryRepository $invoiceEntryRepository, BillingService $billingService): Response
    {
        $invoiceEntry = new InvoiceEntry();
        $invoiceEntry->setInvoice($invoice);
        $invoiceEntry->setEntryType($type);
        $invoiceEntry->setAccount($invoice->getDefaultReceiverAccount()->getValue());
        $invoiceEntry->setMaterialNumber($invoice->getDefaultMaterialNumber());

        $client = $invoice->getClient();

        if ($client) {
            $invoiceEntry->setPrice($client->getStandardPrice());
        }

        if ($type == InvoiceEntryTypeEnum::WORKLOG) {
            $form = $this->createForm(InvoiceEntryWorklogType::class, $invoiceEntry);
        } else {
            $form = $this->createForm(InvoiceEntryType::class, $invoiceEntry);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoiceEntry->setCreatedAt(new \DateTime());
            $invoiceEntry->setUpdatedAt(new \DateTime());
            $invoiceEntryRepository->save($invoiceEntry, true);

            // TODO: Handle this with a doctrine event listener instead.
            $billingService->updateInvoiceEntryTotalPrice($invoiceEntry);

            return $this->redirectToRoute('app_invoices_edit', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice_entry/new.html.twig', [
            'invoice_entry' => $invoiceEntry,
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_invoice_entry_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, InvoiceEntryRepository $invoiceEntryRepository, BillingService $billingService): Response
    {
        if ($invoiceEntry->getEntryType() == InvoiceEntryTypeEnum::WORKLOG) {
            $form = $this->createForm(InvoiceEntryWorklogType::class, $invoiceEntry);
        } else {
            $form = $this->createForm(InvoiceEntryType::class, $invoiceEntry);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoiceEntry->setUpdatedAt(new \DateTime());
            $invoiceEntryRepository->save($invoiceEntry, true);

            // TODO: Handle this with a doctrine event listener instead.
            $billingService->updateInvoiceEntryTotalPrice($invoiceEntry);

            return $this->redirectToRoute('app_invoices_edit', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice_entry/edit.html.twig', [
            'invoice_entry' => $invoiceEntry,
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_entry_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, InvoiceEntryRepository $invoiceEntryRepository, BillingService $billingService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$invoiceEntry->getId(), $request->request->get('_token'))) {
            $invoice = $invoiceEntry->getInvoice();
            $invoiceEntryRepository->remove($invoiceEntry, true);

            // TODO: Handle this with a doctrine event listener instead.
            $billingService->updateInvoiceTotalPrice($invoice);
        }

        return $this->redirectToRoute('app_invoices_edit', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }
}
