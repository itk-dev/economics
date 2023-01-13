<?php

namespace App\Controller\Invoices;

use App\Service\Invoices\InvoiceService;
// use App\Service\PhpSpreadsheetExportService;
// use Billing\Exception\InvoiceException;
// use PhpOffice\PhpSpreadsheet\IOFactory;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ApiController.
 */
#[Route('/invoices')]
class ApiController extends AbstractController
{
    #[Route('/project/{jiraProjectId}', name: 'invoice_index', defaults: ['projectId' => '...'])]
    public function projectAction(InvoiceService $invoiceService, Request $request): JsonResponse
    {
        $jiraProjectId = $request->get('jiraProjectId');
        $project = $invoiceService->getProject($jiraProjectId);

        return new JsonResponse([
            'jiraId' => $project->getJiraId(),
            'jiraKey' => $project->getJiraKey(),
            'name' => $project->getName(),
            'url' => $project->getUrl(),
            'avatarUrl' => $project->getAvatarUrl(),
        ]);
    }

    /**
     * @Route("/projects", name="api_projects")
     */
    public function projectsAction(BillingService $billingService): JsonResponse
    {
        return new JsonResponse($billingService->getProjects());
    }

    /**
     * @Route("/invoice/{invoiceId}", name="api_invoice_get", methods={"GET"})
     * defaults={"invoiceId"="...."})
     */
    public function invoiceGetAction(BillingService $billingService, Request $request): JsonResponse
    {
        $invoiceId = $request->get('invoiceId');
        $result = $billingService->getInvoice($invoiceId);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice", name="api_invoice_post", methods={"POST"})
     */
    public function invoicePostAction(BillingService $billingService, Request $request): JsonResponse
    {
        $invoiceData = json_decode($request->getContent(), true);
        $result = $billingService->postInvoice($invoiceData);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice/{invoiceId}", name="api_invoice_put", methods={"PUT"})
     * defaults={"invoiceId"="...."})
     */
    public function invoicePutAction(BillingService $billingService, Request $request): JsonResponse
    {
        $invoiceData = json_decode($request->getContent(), true);
        $result = $billingService->putInvoice($invoiceData);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice/{invoiceId}", name="api_invoice_delete", methods={"DELETE"})
     * defaults={"invoiceId"="...."})
     */
    public function invoiceDeleteAction(BillingService $billingService, Request $request): JsonResponse
    {
        $invoiceId = $request->get('invoiceId');
        $result = $billingService->deleteInvoice($invoiceId);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoices/{jiraProjectId}", name="api_invoices")
     * defaults={"jiraProjectId"="...."})
     */
    public function invoicesAction(
        BillingService $billingService,
        Request $request
    ) {
        $jiraProjectId = $request->get('jiraProjectId');
        $result = $billingService->getInvoices($jiraProjectId);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoices_all", name="api_invoices_all")
     */
    public function allInvoicesAction(
        BillingService $billingService,
        Request $request
    ) {
        $result = $billingService->getAllInvoices();

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice_entry/{invoiceEntryId}", name="api_invoice_entry_get", methods={"GET"})
     * defaults={"invoiceEntryId"="...."})
     */
    public function invoiceEntryGetAction(
        BillingService $billingService,
        Request $request
    ) {
        $invoiceEntryId = $request->get('invoiceEntryId');
        $result = $billingService->getInvoiceEntry($invoiceEntryId);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice_entry", name="api_invoice_entry_post", methods={"POST"})
     */
    public function invoiceEntryPostAction(
        BillingService $billingService,
        Request $request
    ) {
        $invoiceEntryData = json_decode($request->getContent(), true);
        $result = $billingService->postInvoiceEntry($invoiceEntryData);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice_entry/{invoiceEntryId}", name="api_invoice_entry_put", methods={"PUT"})
     * defaults={"invoiceEntryId"="...."})
     */
    public function invoiceEntryPutAction(
        BillingService $billingService,
        Request $request
    ) {
        $invoiceEntryData = json_decode($request->getContent(), true);
        $result = $billingService->putInvoiceEntry($invoiceEntryData);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice_entry/{invoiceEntryId}", name="api_invoice_entry_delete", methods={"DELETE"})
     * defaults={"invoiceEntryId"="...."})
     */
    public function invoiceEntryDeleteAction(
        BillingService $billingService,
        Request $request
    ) {
        $invoiceEntryId = $request->get('invoiceEntryId');
        $result = $billingService->deleteInvoiceEntry($invoiceEntryId);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice_entries/{invoiceId}", name="api_invoice_entries")
     * defaults={"invoiceId"="...."})
     */
    public function invoiceEntriesAction(
        BillingService $billingService,
        Request $request
    ) {
        $invoiceId = $request->get('invoiceId');
        $result = $billingService->getInvoiceEntries($invoiceId);

        return new JsonResponse($result);
    }

    /**
     * @Route("/invoice_entries_all", name="api_invoice_entries_all")
     */
    public function allInvoiceEntriesAction(
        BillingService $billingService,
        Request $request
    ) {
        $result = $billingService->getAllInvoiceEntries();

        return new JsonResponse($result);
    }

    /**
     * @Route("/project_worklogs/{projectId}", name="api_project_worklogs")
     *
     * @param $projectId
     *
     * @return mixed
     */
    public function getProjectWorklogs(BillingService $billingService, $projectId)
    {
        return new JsonResponse($billingService->getProjectWorklogsWithMetadata($projectId));
    }

    /**
     * @Route("/record_invoice/{invoiceId}", name="api_record_invoice", methods={"PUT"})
     *
     * @param $invoiceId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function recordInvoice(BillingService $billingService, $invoiceId)
    {
        try {
            $invoice = $billingService->recordInvoice($invoiceId);

            return new JsonResponse($invoice);
        } catch (InvoiceException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * @Route("/export_invoices", name="api_export_invoices", methods={"GET"})
     *
     * @param \App\Service\PhpSpreadsheetExportService $phpSpreadsheetExportService
     * @param \Billing\Service\BillingService          $billingService
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportInvoices(PhpSpreadsheetExportService $phpSpreadsheetExportService, Request $request, BillingService $billingService)
    {
        $ids = $request->query->get('ids');

        foreach ($ids as $id) {
            $billingService->markInvoiceAsExported($id);
        }

        $spreadsheet = $billingService->exportInvoicesToSpreadsheet($ids);

        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setDelimiter(';');
        $writer->setEnclosure('');
        $writer->setLineEnding("\r\n");
        $writer->setSheetIndex(0);

        $csvOutput = $phpSpreadsheetExportService->getOutputAsString($writer);

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
     * @Route("/to_accounts", name="api_to_accounts", methods={"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function toAccounts(BillingService $billingService)
    {
        return new JsonResponse($billingService->getToAccounts());
    }

    /**
     * @Route("/material_numbers", name="api_material_numbers", methods={"GET"})
     *
     * @param $boundMaterialNumbers
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function materialNumbers($boundMaterialNumbers)
    {
        return new JsonResponse($boundMaterialNumbers);
    }

    /**
     * @Route("/current_user", name="api_current_user")
     */
    public function currentUserAction(JiraService $jiraService)
    {
        return new JsonResponse($jiraService->getCurrentUser());
    }

    /**
     * @Route("/project_expenses/{projectId}", name="api_expenses_for_project")
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getExpensesForProject(BillingService $billingService, $projectId)
    {
        return new JsonResponse($billingService->getProjectExpensesWithMetadata($projectId));
    }

    /**
     * @Route("/account/project/{projectId}", name="get_accounts_by_project_id")
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getAccountsByProjectId(
        Request $request,
        BillingService $billingService,
        $projectId
    ) {
        return new JsonResponse($billingService->getProjectAccounts($projectId));
    }
}
