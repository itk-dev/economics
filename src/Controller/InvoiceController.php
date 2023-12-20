<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Form\InvoiceFilterType;
use App\Form\InvoiceNewType;
use App\Form\InvoiceRecordType;
use App\Form\InvoiceType;
use App\Model\Invoices\ConfirmData;
use App\Model\Invoices\InvoiceFilterData;
use App\Repository\AccountRepository;
use App\Repository\InvoiceRepository;
use App\Service\BillingService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/invoices')]
class InvoiceController extends AbstractController
{
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
            'submitEndpoint' => $this->generateUrl('app_invoices_export_selection'),
        ]);
    }

    #[Route('/new', name: 'app_invoices_new', methods: ['GET', 'POST'])]
    public function new(Request $request, InvoiceRepository $invoiceRepository): Response
    {
        $invoice = new Invoice();
        $form = $this->createForm(InvoiceNewType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoice->setRecorded(false);
            $invoice->setTotalPrice(0);
            $invoiceRepository->save($invoice, true);

            return $this->redirectToRoute('app_invoices_edit', [
                'id' => $invoice->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoices/new.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_invoices_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService, AccountRepository $accountRepository): Response
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
                'data-account-selector-target' => 'field',
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
                'data-account-selector-target' => 'field',
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
                'row_attr' => ['class' => 'form-row'],
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
                throw new HttpException(400, 'Invoice is recorded, cannot be edited.');
            }

            if (null !== $invoice->getProjectBilling()) {
                throw new HttpException(400, 'Invoice is a part of a project billing, cannot be edited.');
            }

            $invoiceRepository->save($invoice, true);

            // TODO: Handle this with a doctrine event listener instead.
            $billingService->updateInvoiceTotalPrice($invoice);
        }

        return $this->render('invoices/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'invoiceTotalAmount' => array_reduce($invoice->getInvoiceEntries()->toArray(), function ($carry, InvoiceEntry $item) {
                $carry += $item->getAmount();

                return $carry;
            }, 0.0),
        ]);
    }

    #[Route('/{id}/generate-description', name: 'app_invoices_generate_description', methods: ['GET'])]
    public function generateDescription(Invoice $invoice, $defaultInvoiceDescriptionTemplate): JsonResponse
    {
        $description = $defaultInvoiceDescriptionTemplate;

        // Default description.
        if (!empty($invoice->getClient())) {
            $projectLeadName = $invoice->getClient()?->getProjectLeadName() ?? null;
            $projectLeadMail = $invoice->getClient()?->getProjectLeadMail() ?? null;

            if ($projectLeadName) {
                $description = str_replace('%name%', $projectLeadName, $description);
            }

            if ($projectLeadMail) {
                $description = str_replace('%email%', $projectLeadMail, $description);
            }
        }

        return new JsonResponse(['description' => $description]);
    }

    #[Route('/{id}', name: 'app_invoices_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$invoice->getId(), $token)) {
            if ($invoice->isRecorded()) {
                throw new HttpException(400, 'Invoice is put on record, cannot be deleted.');
            }

            if (null !== $invoice->getProjectBilling()) {
                throw new HttpException(400, 'Invoice is a part of a project billing, cannot be deleted.');
            }

            $invoiceRepository->remove($invoice, true);
        }

        return $this->redirectToRoute('app_invoices_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Put an invoice on record. After this invoice cannot be deleted.
     *
     * @throws \Exception
     */
    #[Route('/{id}/record', name: 'app_invoices_record', methods: ['GET', 'POST'])]
    public function record(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService): Response
    {
        $recordData = new ConfirmData();
        $form = $this->createForm(InvoiceRecordType::class, $recordData);
        $form->handleRequest($request);

        $errors = $billingService->getInvoiceRecordableErrors($invoice);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $invoice->getProjectBilling()) {
                throw new HttpException(400, 'Invoice is a part of a project billing, cannot be put on record.');
            }

            if ($recordData->confirmed) {
                $billingService->recordInvoice($invoice);
            }

            return $this->redirectToRoute('app_invoices_edit', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoices/record.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'errors' => $errors,
        ]);
    }

    /**
     * Show the invoice export data.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/{id}/show-export', name: 'app_invoices_show_export', methods: ['GET'])]
    public function showExport(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService): Response
    {
        $spreadsheet = $billingService->exportInvoicesToSpreadsheet([$invoice->getId()]);

        $writer = IOFactory::createWriter($spreadsheet, 'Html');

        $html = $billingService->getSpreadsheetOutputAsString($writer);

        if (empty($html)) {
            $html = '<html lang="da" />';
        }

        // Extract body content.
        $d = new \DOMDocument();
        $mock = new \DOMDocument();
        $d->loadHTML($html);
        /** @var \DOMNode $body */
        $body = $d->getElementsByTagName('div')->item(0);

        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if ('table' == $child->tagName) {
                    $child->setAttribute('class', 'table table-export');
                }
            }
            $mock->appendChild($mock->importNode($child, true));
        }

        return $this->render('invoices/export_show.html.twig', [
            'html' => $mock->saveHTML(),
            'invoice' => $invoice,
        ]);
    }

    /**
     * Export to a .csv file.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/{id}/export', name: 'app_invoices_export', methods: ['GET'])]
    public function export(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService): Response
    {
        if (!$invoice->isRecorded()) {
            throw new HttpException(400, 'Invoice cannot be exported before it is on record.');
        }

        if (null !== $invoice->getProjectBilling()) {
            throw new HttpException(400, 'Invoice is a part of a project billing, cannot be exported.');
        }

        // Mark invoice as exported.
        $invoice->setExportedDate(new \DateTime());
        $invoiceRepository->save($invoice, true);

        $spreadsheet = $billingService->exportInvoicesToSpreadsheet([$invoice->getId()]);

        /** @var Csv $writer */
        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setDelimiter(';');
        $writer->setEnclosure('');
        $writer->setLineEnding("\r\n");
        $writer->setSheetIndex(0);

        $csvOutput = $billingService->getSpreadsheetOutputAsString($writer);

        // Change encoding to Windows-1252.
        $csvOutputEncoded = mb_convert_encoding($csvOutput, 'Windows-1252');

        $response = new Response($csvOutputEncoded);
        $filename = 'invoices-'.date('d-m-Y').'.csv';

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Export a selection of invoices to a .csv file.
     *
     * The ids of the invoices should be supplied as id query params to the request.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/export-selection', name: 'app_invoices_export_selection', methods: ['GET'])]
    public function exportSelection(Request $request, InvoiceRepository $invoiceRepository, BillingService $billingService, EntityManagerInterface $entityManager): Response
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
                    throw new HttpException(400, 'Invoice cannot be exported before it is on record.');
                }

                if (null !== $invoice->getProjectBilling()) {
                    throw new HttpException(400, 'Invoice is a part of a project billing, cannot be exported.');
                }

                // Mark invoice as exported.
                $invoice->setExportedDate(new \DateTime());
                $invoiceRepository->save($invoice);
            }
        }

        $entityManager->flush();

        $spreadsheet = $billingService->exportInvoicesToSpreadsheet($ids);

        /** @var Csv $writer */
        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setDelimiter(';');
        $writer->setEnclosure('');
        $writer->setLineEnding("\r\n");
        $writer->setSheetIndex(0);

        $csvOutput = $billingService->getSpreadsheetOutputAsString($writer);

        // Change encoding to Windows-1252.
        $csvOutputEncoded = mb_convert_encoding($csvOutput, 'Windows-1252');

        $response = new Response($csvOutputEncoded);
        $filename = 'invoices-'.date('d-m-Y').'.csv';

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
