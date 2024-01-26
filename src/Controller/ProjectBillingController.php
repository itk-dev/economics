<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\ProjectBilling;
use App\Exception\EconomicsException;
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/project-billing')]
class ProjectBillingController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

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

    /**
     * @throws EconomicsException
     */
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
                throw new EconomicsException($this->translator->trans('exception.project_billing_on_record_cannot_edit'), 400);
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

    /**
     * @throws EconomicsException
     */
    #[Route('/{id}', name: 'app_project_billing_delete', methods: ['POST'])]
    public function delete(Request $request, ProjectBilling $projectBilling, ProjectBillingRepository $projectBillingRepository, InvoiceRepository $invoiceRepository): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$projectBilling->getId(), $token)) {
            if ($projectBilling->isRecorded()) {
                throw new EconomicsException($this->translator->trans('exception.project_billing_on_record_cannot_delete'), 400);
            }

            foreach ($projectBilling->getInvoices() as $invoice) {
                if (!$invoice->isRecorded()) {
                    $invoiceRepository->remove($invoice);
                } else {
                    throw new EconomicsException($this->translator->trans('exception.project_billing_invoice_on_record_cannot_delete', ['%invoiceId%' => $invoice->getId()]), 400);
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
    public function record(Request $request, ProjectBilling $projectBilling, ProjectBillingService $projectBillingService, BillingService $billingService): Response
    {
        $recordData = new ConfirmData();
        $form = $this->createForm(ProjectBillingRecordType::class, $recordData);
        $form->handleRequest($request);

        $invoiceErrors = [];

        foreach ($projectBilling->getInvoices() as $invoice) {
            $errors = $billingService->getInvoiceRecordableErrors($invoice);

            $invoiceId = $invoice->getId();
            if (count($errors) > 0 && null != $invoiceId) {
                $invoiceErrors[$invoiceId] = $errors;
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if (count($invoiceErrors) > 0) {
                throw new EconomicsException($this->translator->trans('exception.invoice_errors_exit_cannot_put_on_record'), 400);
            }

            if ($recordData->confirmed) {
                $projectBillingService->recordProjectBilling($projectBilling);
            }

            return $this->redirectToRoute('app_project_billing_edit', ['id' => $projectBilling->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_billing/record.html.twig', [
            'projectBilling' => $projectBilling,
            'form' => $form,
            'invoiceErrors' => $invoiceErrors,
        ]);
    }

    /**
     * Preview the export.
     *
     * @throws EconomicsException
     */
    #[Route('/{id}/show-export', name: 'app_project_billing_show_export', methods: ['GET'])]
    public function showExport(Request $request, ProjectBilling $projectBilling, BillingService $billingService): Response
    {
        $ids = array_map(function ($invoice) {
            return $invoice->getId();
        }, $projectBilling->getInvoices()->toArray());

        $html = $billingService->generateSpreadsheetHtml($ids);

        return $this->render('project_billing/export_show.html.twig', [
            'projectBilling' => $projectBilling,
            'html' => $html,
        ]);
    }

    /**
     * Export the project billing to .csv.
     *
     * @throws EconomicsException
     */
    #[Route('/{id}/export', name: 'app_project_billing_export', methods: ['GET'])]
    public function export(Request $request, ProjectBilling $projectBilling, InvoiceRepository $invoiceRepository, BillingService $billingService, ProjectBillingRepository $projectBillingRepository): Response
    {
        $invoices = $projectBilling->getInvoices();

        // Filter invoices by client.type if type query parameter is set.
        $type = $request->query->get('type');
        if (null !== $type) {
            $invoices = $invoices->filter(fn (Invoice $invoice) => $invoice->getClient()?->getType()?->value == $type);
        }

        // Mark invoice as exported.
        foreach ($invoices as $invoice) {
            $invoice->setExportedDate(new \DateTime());
            $invoiceRepository->save($invoice, true);
        }

        $projectBilling->setExportedDate(new \DateTime());
        $projectBillingRepository->save($projectBilling, true);

        $ids = array_map(function ($invoice) {
            return $invoice->getId();
        }, $invoices->toArray());

        return $billingService->generateSpreadsheetCsvResponse($ids);
    }
}
