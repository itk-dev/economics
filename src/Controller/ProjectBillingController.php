<?php

namespace App\Controller;

use App\Entity\ProjectBilling;
use App\Form\ProjectBillingFilterType;
use App\Form\ProjectBillingRecordType;
use App\Form\ProjectBillingType;
use App\Message\CreateProjectBillingMessage;
use App\Message\UpdateProjectBillingMessage;
use App\Model\Invoices\ConfirmData;
use App\Model\Invoices\ProjectBillingFilterData;
use App\Repository\InvoiceRepository;
use App\Repository\IssueRepository;
use App\Repository\ProjectBillingRepository;
use App\Service\BillingService;
use App\Service\ProjectBillingService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/project-billing')]
class ProjectBillingController extends AbstractController
{
    #[Route('/', name: 'app_project_billing_index', methods: ['GET'])]
    public function index(Request $request, ProjectBillingRepository $projectBillingRepository): Response
    {
        $projectBillingFilterData = new ProjectBillingFilterData();
        $form = $this->createForm(ProjectBillingFilterType::class, $projectBillingFilterData);
        $form->handleRequest($request);

        $pagination = $projectBillingRepository->getFilteredPagination($projectBillingFilterData, $request->query->getInt('page', 1));

        return $this->render('project_billing/index.html.twig', [
            'projectBillings' => $pagination,
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'app_project_billing_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ProjectBillingRepository $projectBillingRepository, MessageBusInterface $bus, string $projectBillingDefaultDescription): Response
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setDescription($projectBillingDefaultDescription);

        $form = $this->createForm(ProjectBillingType::class, $projectBilling);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectBilling->setRecorded(false);
            $projectBillingRepository->save($projectBilling, true);

            // Emit create billing message.
            $id = $projectBilling->getId();
            if (null !== $id) {
                $bus->dispatch(new CreateProjectBillingMessage($id));
            }

            return $this->redirectToRoute('app_project_billing_edit', [
                'id' => $id,
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_billing/new.html.twig', [
            'projectBilling' => $projectBilling,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_billing_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ProjectBilling $projectBilling, ProjectBillingRepository $projectBillingRepository, MessageBusInterface $bus, IssueRepository $issueRepository): Response
    {
        $options = [];
        if ($projectBilling->isRecorded()) {
            $options = ['disabled' => true];
        }

        $form = $this->createForm(ProjectBillingType::class, $projectBilling, $options);
        $form->handleRequest($request);

        $issuesWithoutAccounts = $issueRepository->getIssuesNotIncludedInProjectBilling($projectBilling);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($projectBilling->isRecorded()) {
                throw new HttpException(400, 'ProjectBilling is recorded, cannot be deleted.');
            }

            $projectBillingRepository->save($projectBilling, true);

            // Emit create billing message.
            $id = $projectBilling->getId();
            if (null !== $id) {
                $bus->dispatch(new UpdateProjectBillingMessage($id));
            }

            return $this->redirectToRoute('app_project_billing_edit', [
                'id' => $id,
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_billing/edit.html.twig', [
            'projectBilling' => $projectBilling,
            'form' => $form,
            'issuesWithoutAccounts' => $issuesWithoutAccounts,
        ]);
    }

    #[Route('/{id}', name: 'app_project_billing_delete', methods: ['POST'])]
    public function delete(Request $request, ProjectBilling $projectBilling, ProjectBillingRepository $projectBillingRepository, InvoiceRepository $invoiceRepository): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$projectBilling->getId(), $token)) {
            if ($projectBilling->isRecorded()) {
                throw new HttpException(400, 'ProjectBilling is recorded, cannot be deleted.');
            }

            foreach ($projectBilling->getInvoices() as $invoice) {
                if (!$invoice->isRecorded()) {
                    $invoiceRepository->remove($invoice);
                } else {
                    $invoiceName = $invoice->getName();

                    throw new HttpException(400, "Invoice \"$invoiceName\" is recorded, cannot be deleted.");
                }
            }

            $projectBillingRepository->remove($projectBilling, true);
        }

        return $this->redirectToRoute('app_project_billing_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Put project billing on record.
     *
     * @throws \Exception
     */
    #[Route('/{id}/record', name: 'app_project_billing_record', methods: ['GET', 'POST'])]
    public function record(Request $request, ProjectBilling $projectBilling, ProjectBillingService $projectBillingService): Response
    {
        $recordData = new ConfirmData();
        $form = $this->createForm(ProjectBillingRecordType::class, $recordData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($recordData->confirmed) {
                $projectBillingService->recordProjectBilling($projectBilling);
            }

            return $this->redirectToRoute('app_project_billing_edit', ['id' => $projectBilling->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_billing/record.html.twig', [
            'projectBilling' => $projectBilling,
            'form' => $form,
        ]);
    }

    /**
     * Preview the export.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    #[Route('/{id}/show-export', name: 'app_project_billing_show_export', methods: ['GET'])]
    public function showExport(Request $request, ProjectBilling $projectBilling, BillingService $billingService): Response
    {
        $ids = array_map(function ($invoice) {
            return $invoice->getId();
        }, $projectBilling->getInvoices()->toArray());

        $spreadsheet = $billingService->exportInvoicesToSpreadsheet($ids);

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

        return $this->render('project_billing/export_show.html.twig', [
            'html' => $mock->saveHTML(),
            'projectBilling' => $projectBilling,
        ]);
    }

    /**
     * Export the project billing to .csv.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    #[Route('/{id}/export', name: 'app_project_billing_export', methods: ['GET'])]
    public function export(Request $request, ProjectBilling $projectBilling, InvoiceRepository $invoiceRepository, BillingService $billingService, ProjectBillingRepository $projectBillingRepository): Response
    {
        // Mark invoice as exported.
        foreach ($projectBilling->getInvoices() as $invoice) {
            $invoice->setExportedDate(new \DateTime());
            $invoiceRepository->save($invoice, true);
        }

        $projectBilling->setExportedDate(new \DateTime());
        $projectBillingRepository->save($projectBilling, true);

        $ids = array_map(function ($invoice) {
            return $invoice->getId();
        }, $projectBilling->getInvoices()->toArray());

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
