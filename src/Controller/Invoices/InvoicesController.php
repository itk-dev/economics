<?php

namespace App\Controller\Invoices;

use App\Entity\Account;
use App\Entity\Invoice;
use App\Form\Invoices\InvoiceFilterType;
use App\Form\Invoices\InvoiceNewType;
use App\Form\Invoices\InvoiceRecordType;
use App\Form\Invoices\InvoiceType;
use App\Model\Invoices\InvoiceFilterData;
use App\Model\Invoices\InvoiceRecordData;
use App\Repository\AccountRepository;
use App\Repository\InvoiceRepository;
use App\Service\Invoices\BillingService;
use DOMNode;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/invoices')]
class InvoicesController extends AbstractController
{
    #[Route('/', name: 'app_invoices_index', methods: ['GET'])]
    public function index(Request $request, InvoiceRepository $invoiceRepository, PaginatorInterface $paginator): Response
    {
        $qb = $invoiceRepository->createQueryBuilder('invoice');

        $invoiceFilterData = new InvoiceFilterData();
        $form = $this->createForm(InvoiceFilterType::class, $invoiceFilterData);
        $form->handleRequest($request);

        $qb->andWhere('invoice.recorded = :recorded')->setParameter('recorded', $invoiceFilterData->recorded ?? false);

        if (isset($invoiceFilterData->createdBy)) {
            $qb->andWhere('invoice.createdBy LIKE :createdBy')->setParameter('createdBy', $invoiceFilterData->createdBy);
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10,
            ['defaultSortFieldName' => 'invoice.createdAt', 'defaultSortDirection' => 'desc']
        );

        return $this->render('invoices/index.html.twig', [
            'form' => $form,
            'invoices' => $pagination,
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
            $invoice->setCreatedAt(new \DateTime());
            $invoice->setUpdatedAt(new \DateTime());
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
        if ($invoice->getPaidByAccount()) {
            if (!in_array($invoice->getPaidByAccount(), $paidByAccountChoices)) {
                $paidByAccountChoices[$invoice->getPaidByAccount()] = $invoice->getPaidByAccount();
            }
        }

        // Backwards compatible with JiraEconomics.
        $defaultReceiverAccountChoices = $accountChoices;
        if ($invoice->getDefaultReceiverAccount()) {
            if (!in_array($invoice->getDefaultReceiverAccount(), $defaultReceiverAccountChoices)) {
                $defaultReceiverAccountChoices[$invoice->getDefaultReceiverAccount()] = $invoice->getDefaultReceiverAccount();
            }
        }

        $form->add('paidByAccount',  ChoiceType::class, [
            'required' => false,
            'label' => 'invoices.paid_by_account',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row form-choices'],
            'attr' => [
                'class' => 'form-element',
                'data-account-selector-target' => 'field',
            ],
            'choices' => $paidByAccountChoices,
            'help' => 'invoices.payer_account_helptext',
        ]);

        $form->add('defaultReceiverAccount',  ChoiceType::class, [
            'required' => false,
            'label' => 'invoices.default_receiver_account',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row form-choices'],
            'attr' => [
                'class' => 'form-element',
                'data-account-selector-target' => 'field',
            ],
            'choices' => $defaultReceiverAccountChoices,
            'help' => 'invoices.default_receiver_account_helptext',
        ]);

        if ($invoice->getProject()) {
            $form->add('client',  null, [
                'label' => 'invoices.client',
                'label_attr' => ['class' => 'label'],
                'row_attr' => ['class' => 'form-row'],
                'attr' => ['class' => 'form-element'],
                'help' => 'invoices.client_helptext',
                'choices' => $invoice->getProject()->getClients(),
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($invoice->isRecorded()) {
                throw new HttpException(400, "Invoice is recorded, cannot be edited.");
            }

            $invoice->setUpdatedAt(new \DateTime());
            $invoiceRepository->save($invoice, true);

            // TODO: Handle this with a doctrine event listener instead.
            $billingService->updateInvoiceTotalPrice($invoice);
      }

        return $this->render('invoices/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/{id}/record', name: 'app_invoices_record', methods: ['GET', 'POST'])]
    public function record(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService): Response
    {
        $recordData = new InvoiceRecordData();
        $form = $this->createForm(InvoiceRecordType::class, $recordData);
        $form->handleRequest($request);

        $errors = $billingService->getInvoiceRecordableErrors($invoice);

        if ($form->isSubmitted() && $form->isValid()) {
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
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/{id}/show-export', name: 'app_invoices_show_export', methods: ['GET'])]
    public function showExport(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService): Response
    {
        $spreadsheet = $billingService->exportInvoicesToSpreadsheet([$invoice->getId()]);

        $writer = IOFactory::createWriter($spreadsheet, 'Html');

        $html = $billingService->getSpreadsheetOutputAsString($writer);

        // Extract body content.
        $d = new \DOMDocument();
        $mock = new \DOMDocument();
        $d->loadHTML($html);
        /** @var DOMNode $body */
        $body = $d->getElementsByTagName('div')->item(0);
        /** @var DOMNode $child */
        foreach ($body->childNodes as $child) {
            if (isset($child->tagName) && 'style' === $child->tagName) {
                continue;
            }
            if (isset($child->tagName) && 'table' === $child->tagName) {
                $child->setAttribute('class', 'table table-export');
            }
            $mock->appendChild($mock->importNode($child, true));
        }

        return $this->render('invoices/export_show.html.twig', [
            'html' => $mock->saveHTML(),
            'invoice' => $invoice,
        ]);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/{id}/export', name: 'app_invoices_export', methods: ['GET'])]
    public function export(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService): Response
    {
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

    #[Route('/{id}', name: 'app_invoices_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$invoice->getId(), $token)) {
            if ($invoice->isRecorded()) {
                throw new HttpException(400, "Invoice is recorded, cannot be deleted.");
            }

            $invoiceRepository->remove($invoice, true);
        }

        return $this->redirectToRoute('app_invoices_index', [], Response::HTTP_SEE_OTHER);
    }
}
