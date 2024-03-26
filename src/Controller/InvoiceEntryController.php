<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Enum\InvoiceEntryTypeEnum;
use App\Exception\EconomicsException;
use App\Form\InvoiceEntryType;
use App\Form\InvoiceEntryWorklogType;
use App\Repository\InvoiceEntryRepository;
use App\Service\BillingService;
use App\Service\ClientHelper;
use App\Service\ViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/invoices/{invoice}/entries')]
class InvoiceEntryController extends AbstractController
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly TranslatorInterface $translator,
        private readonly ViewService $viewService,
        private readonly ClientHelper $clientHelper
    ) {
    }

    /**
     * @throws EconomicsException
     */
    #[Route('/new/{type}', name: 'app_invoice_entry_new', methods: ['GET', 'POST'])]
    #[IsGranted('EDIT', 'invoice')]
    public function new(Request $request, Invoice $invoice, InvoiceEntryTypeEnum $type, InvoiceEntryRepository $invoiceEntryRepository): Response
    {
        if ($invoice->isRecorded()) {
            throw new EconomicsException($this->translator->trans('exception.invoice_entry_invoice_on_record_cannot_add_entries'), 400);
        }

        $invoiceEntry = new InvoiceEntry();
        $invoice->addInvoiceEntry($invoiceEntry);
        $invoiceEntry->setEntryType($type);
        $invoiceEntry->setAccount($invoice->getDefaultReceiverAccount());
        $invoiceEntry->setMaterialNumber($invoice->getDefaultMaterialNumber());

        $client = $invoice->getClient();

        $invoiceEntry->setPrice($this->clientHelper->getStandardPrice($client));

        if (InvoiceEntryTypeEnum::WORKLOG == $type) {
            $form = $this->createForm(InvoiceEntryWorklogType::class, $invoiceEntry);
        } else {
            $form = $this->createForm(InvoiceEntryType::class, $invoiceEntry);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoiceEntryRepository->save($invoiceEntry, true);

            // TODO: Handle this with a doctrine event listener instead.
            $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);

            if (InvoiceEntryTypeEnum::MANUAL == $invoiceEntry->getEntryType()) {
                return $this->redirectToRoute('app_invoices_edit', $this->viewService->addView(['id' => $invoice->getId()]), Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_invoice_entry_edit', $this->viewService->addView(['id' => $invoiceEntry->getId(), 'invoice' => $invoice->getId()]), Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice_entry/new.html.twig', $this->viewService->addView([
            'invoice_entry' => $invoiceEntry,
            'invoice' => $invoice,
            'form' => $form,
        ]));
    }

    /**
     * @throws EconomicsException
     */
    #[Route('/{id}/edit', name: 'app_invoice_entry_edit', methods: ['GET', 'POST'])]
    #[IsGranted('EDIT', 'invoiceEntry')]
    public function edit(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, InvoiceEntryRepository $invoiceEntryRepository): Response
    {
        $options = [];
        if ($invoice->isRecorded()) {
            $options['disabled'] = true;
        }

        if (InvoiceEntryTypeEnum::WORKLOG == $invoiceEntry->getEntryType()) {
            $form = $this->createForm(InvoiceEntryWorklogType::class, $invoiceEntry, $options);
        } else {
            $form = $this->createForm(InvoiceEntryType::class, $invoiceEntry);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($invoice->isRecorded()) {
                throw new EconomicsException($this->translator->trans('exception.invoice_entry_invoice_on_record_cannot_edit_entries'), 400);
            }

            $invoiceEntryRepository->save($invoiceEntry, true);

            // TODO: Handle this with a doctrine event listener instead.
            $this->billingService->updateInvoiceEntryTotalPrice($invoiceEntry);
        }

        return $this->render('invoice_entry/edit.html.twig', $this->viewService->addView([
            'invoice_entry' => $invoiceEntry,
            'invoice' => $invoice,
            'form' => $form,
        ]));
    }

    /**
     * @throws EconomicsException
     */
    #[Route('/{id}', name: 'app_invoice_entry_delete', methods: ['POST'])]
    #[IsGranted('EDIT', 'invoiceEntry')]
    public function delete(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, InvoiceEntryRepository $invoiceEntryRepository): Response
    {
        if ($invoice->isRecorded()) {
            throw new EconomicsException($this->translator->trans('exception.invoice_entry_invoice_on_record_cannot_delete_entries'), 400);
        }

        if ($this->isCsrfTokenValid('delete'.$invoiceEntry->getId(), (string) $request->request->get('_token'))) {
            $invoiceEntryRepository->remove($invoiceEntry, true);

            // TODO: Handle this with a doctrine event listener instead.
            $this->billingService->updateInvoiceTotalPrice($invoice);
        }

        return $this->redirectToRoute('app_invoices_edit', $this->viewService->addView(['id' => $invoice->getId()]), Response::HTTP_SEE_OTHER);
    }
}
