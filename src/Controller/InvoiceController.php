<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Exception\EconomicsException;
use App\Form\InvoiceFilterType;
use App\Form\InvoiceNewType;
use App\Form\InvoiceRecordType;
use App\Form\InvoiceType;
use App\Model\Invoices\ConfirmData;
use App\Model\Invoices\InvoiceFilterData;
use App\Repository\AccountRepository;
use App\Repository\InvoiceEntryRepository;
use App\Repository\InvoiceRepository;
use App\Service\BillingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{viewId}/invoices')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/', name: 'app_invoices_index', methods: ['GET'])]
    public function index(Request $request, InvoiceRepository $invoiceRepository): Response
    {
        $invoiceFilterData = new InvoiceFilterData();
        $form = $this->createForm(InvoiceFilterType::class, $invoiceFilterData);
        $form->handleRequest($request);

        $pagination = $invoiceRepository->getFilteredPagination($invoiceFilterData, $request->query->getInt('page', 1));

        return $this->render('invoices/index.html.twig', [
            'form' => $form,
            'invoices' => $pagination,
            'invoiceFilterData' => $invoiceFilterData,
            'submitEndpoint' => $this->generateUrl('app_invoices_export_selection', ['viewId' => $request->attributes->get('viewId')]),
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    #[Route('/new', name: 'app_invoices_new', methods: ['GET', 'POST'])]
    public function new(Request $request, InvoiceRepository $invoiceRepository, string $invoiceDefaultReceiverAccount): Response
    {
        $invoice = new Invoice();
        $form = $this->createForm(InvoiceNewType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!empty($invoiceDefaultReceiverAccount)) {
                $invoice->setDefaultReceiverAccount($invoiceDefaultReceiverAccount);
            }

            $invoice->setRecorded(false);
            $invoice->setTotalPrice(0);
            $invoiceRepository->save($invoice, true);

            return $this->redirectToRoute('app_invoices_edit', [
                'id' => $invoice->getId(),
                'viewId' => $request->attributes->get('viewId'),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoices/new.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    /**
     * @throws EconomicsException
     */
    #[Route('/{id}/edit', name: 'app_invoices_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, AccountRepository $accountRepository, InvoiceEntryRepository $invoiceEntryRepository): Response
    {
        $options = [];
        if ($invoice->isRecorded()) {
            $options = ['disabled' => true];
        }

        $form = $this->createForm(InvoiceType::class, $invoice, $options);

        $accountChoices = $accountRepository->getAllChoices();

        // Backwards compatible with JiraEconomics.
        $paidByAccountChoices = $accountChoices;
        $paidByAccountCurrentValue = $invoice->getPaidByAccount();
        if (!empty($paidByAccountCurrentValue)) {
            if (!in_array($paidByAccountCurrentValue, $paidByAccountChoices)) {
                $paidByAccountChoices[$paidByAccountCurrentValue] = $paidByAccountCurrentValue;
            }
        }

        // Backwards compatible with JiraEconomics.
        $defaultReceiverAccountChoices = $accountChoices;
        $defaultReceiverAccountCurrentValue = $invoice->getDefaultReceiverAccount();
        if (!empty($defaultReceiverAccountCurrentValue)) {
            if (!in_array($defaultReceiverAccountCurrentValue, $defaultReceiverAccountChoices)) {
                $defaultReceiverAccountChoices[$defaultReceiverAccountCurrentValue] = $defaultReceiverAccountCurrentValue;
            }
        }

        $form->add('paidByAccount', ChoiceType::class, [
            'required' => false,
            'label' => 'invoices.paid_by_account',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row form-choices'],
            'attr' => [
                'class' => 'form-element',
                'data-choices-target' => 'choices',
            ],
            'choices' => $paidByAccountChoices,
            'help' => 'invoices.payer_account_helptext',
        ]);

        $form->add('defaultReceiverAccount', ChoiceType::class, [
            'required' => false,
            'label' => 'invoices.default_receiver_account',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row form-choices'],
            'attr' => [
                'class' => 'form-element',
                'data-choices-target' => 'choices',
            ],
            'choices' => $defaultReceiverAccountChoices,
            'help' => 'invoices.default_receiver_account_helptext',
        ]);

        $project = $invoice->getProject();

        if (!is_null($project)) {
            $clients = $project->getClients();

            $form->add('client', null, [
                'label' => 'invoices.client',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row form-choices'],
                'attr' => [
                    'class' => 'form-element',
                    'data-choices-target' => 'choices',
                ],
                'help' => 'invoices.client_helptext',
                'choices' => $clients,
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($invoice->isRecorded()) {
                throw new EconomicsException($this->translator->trans('exception.invoices_on_record_cannot_edit'), 400);
            }

            if (null !== $invoice->getProjectBilling()) {
                throw new EconomicsException($this->translator->trans('exception.invoices_part_of_project_billing_cannot_edit'), 400);
            }

            $invoiceRepository->save($invoice, true);

            // Update values in invoice entries.
            foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
                $invoiceEntry->setMaterialNumber($invoice->getDefaultMaterialNumber());
                $invoiceEntry->setAccount($invoice->getDefaultReceiverAccount());
                $invoiceEntryRepository->save($invoiceEntry, true);
            }

            // TODO: Handle this with a doctrine event listener instead.
            $this->billingService->updateInvoiceTotalPrice($invoice);
        }

        // Only allow adding entries when material number and receiver account have been set.
        $allowAddingEntries = !empty($invoice->getDefaultReceiverAccount())
            && !empty($invoice->getDefaultMaterialNumber())
            && !empty($invoice->getDefaultMaterialNumber()->value);

        return $this->render('invoices/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'allowAddingEntries' => $allowAddingEntries,
            'invoiceTotalAmount' => array_reduce($invoice->getInvoiceEntries()->toArray(), function ($carry, InvoiceEntry $item) {
                $carry += $item->getAmount();

                return $carry;
            }, 0.0),
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    #[Route('/{id}/generate-description', name: 'app_invoices_generate_description', methods: ['GET'])]
    public function generateDescription(Invoice $invoice, $defaultInvoiceDescriptionTemplate): JsonResponse
    {
        $client = $invoice->getClient();

        if (!empty($client) && !(empty($client->getProjectLeadName())) && !empty($client->getProjectLeadMail())) {
            $description = $defaultInvoiceDescriptionTemplate;

            $description = str_replace('%name%', $client->getProjectLeadName() ?? '', $description);
            $description = str_replace('%email%', $client->getProjectLeadMail() ?? '', $description);
        }

        return new JsonResponse(['description' => $description ?? null]);
    }

    /**
     * @throws EconomicsException
     */
    #[Route('/{id}', name: 'app_invoices_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$invoice->getId(), $token)) {
            if ($invoice->isRecorded()) {
                throw new EconomicsException($this->translator->trans('exception.invoices_delete_on_record'), 400);
            }

            if (null !== $invoice->getProjectBilling()) {
                throw new EconomicsException($this->translator->trans('exception.invoices_delete_part_of_project_billing'), 400);
            }

            $invoiceRepository->remove($invoice, true);
        }

        return $this->redirectToRoute('app_invoices_index', ['viewId' => $request->attributes->get('viewId')], Response::HTTP_SEE_OTHER);
    }

    /**
     * Put an invoice on record. After this invoice cannot be deleted.
     *
     * @throws EconomicsException
     */
    #[Route('/{id}/record', name: 'app_invoices_record', methods: ['GET', 'POST'])]
    public function record(Request $request, Invoice $invoice): Response
    {
        $recordData = new ConfirmData();
        $form = $this->createForm(InvoiceRecordType::class, $recordData);
        $form->handleRequest($request);

        $errors = $this->billingService->getInvoiceRecordableErrors($invoice);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $invoice->getProjectBilling()) {
                throw new EconomicsException($this->translator->trans('exception.invoices_record_part_of_project_billing'), 400);
            }

            foreach ($invoice->getInvoiceEntries() as $invoiceEntry) {
                if (empty($invoiceEntry->getAmount())) {
                    throw new EconomicsException($this->translator->trans('exception.invoices_record_entry_amount_not_set', ['%invoiceEntryUrl%' => $this->generateUrl('app_invoice_entry_edit', ['id' => $invoiceEntry->getId(), 'invoice' => $invoice->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]), 400);
                }
            }

            if ($recordData->confirmed) {
                $this->billingService->recordInvoice($invoice);
            }

            return $this->redirectToRoute('app_invoices_edit', ['viewId' => $request->attributes->get('viewId'), 'id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoices/record.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'errors' => $errors,
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    /**
     * Show the invoice export data.
     *
     * @throws EconomicsException
     */
    #[Route('/{id}/show-export', name: 'app_invoices_show_export', methods: ['GET'])]
    public function showExport(Request $request, Invoice $invoice): Response
    {
        $html = $this->billingService->generateSpreadsheetHtml([$invoice->getId()]);

        return $this->render('invoices/export_show.html.twig', [
            'invoice' => $invoice,
            'html' => $html,
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    /**
     * Export to a .csv file.
     *
     * @throws EconomicsException
     */
    #[Route('/{id}/export', name: 'app_invoices_export', methods: ['GET'])]
    public function export(Invoice $invoice, InvoiceRepository $invoiceRepository): Response
    {
        if (!$invoice->isRecorded()) {
            throw new EconomicsException($this->translator->trans('exception.invoices_export_must_be_on_record'), 400);
        }

        if (null !== $invoice->getProjectBilling()) {
            throw new EconomicsException($this->translator->trans('exception.invoices_export_part_of_project_billing'), 400);
        }

        // Mark invoice as exported.
        $invoice->setExportedDate(new \DateTime());
        $invoiceRepository->save($invoice, true);

        return $this->billingService->generateSpreadsheetCsvResponse([$invoice->getId()]);
    }

    /**
     * Export a selection of invoices to a .csv file.
     *
     * The ids of the invoices should be supplied as id query params to the request.
     *
     * @throws EconomicsException
     */
    #[Route('/export-selection', name: 'app_invoices_export_selection', methods: ['GET'])]
    public function exportSelection(Request $request, InvoiceRepository $invoiceRepository, EntityManagerInterface $entityManager): Response
    {
        $queryIds = $request->query->get('ids');

        $ids = [];

        if (is_string($queryIds)) {
            $ids = explode(',', $queryIds);
        }

        foreach ($ids as $id) {
            $invoice = $invoiceRepository->find($id);

            if (null != $invoice) {
                if (!$invoice->isRecorded()) {
                    throw new EconomicsException($this->translator->trans('exception.invoices_export_must_be_on_record'), 400);
                }

                if (null !== $invoice->getProjectBilling()) {
                    throw new EconomicsException($this->translator->trans('exception.invoices_export_part_of_project_billing'), 400);
                }

                // Mark invoice as exported.
                $invoice->setExportedDate(new \DateTime());
                $invoiceRepository->save($invoice);
            }
        }

        $entityManager->flush();

        return $this->billingService->generateSpreadsheetCsvResponse($ids);
    }
}
