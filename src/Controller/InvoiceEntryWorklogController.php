<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Version;
use App\Enum\InvoiceEntryTypeEnum;
use App\Form\InvoiceEntryWorklogFilterType;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\WorklogRepository;
use App\Service\BillingService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/invoices/{invoice}/entries')]
class InvoiceEntryWorklogController extends AbstractController
{
    /**
     * @throws \Exception
     */
    #[Route('/{invoiceEntry}/worklogs', name: 'app_invoice_entry_worklogs', methods: ['GET', 'POST'])]
    public function worklogs(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, WorklogRepository $worklogRepository): Response
    {
        if (InvoiceEntryTypeEnum::WORKLOG != $invoiceEntry->getEntryType()) {
            throw new \Exception('Invoice entry is not a WORKLOG type.');
        }

        $project = $invoice->getProject();

        if (is_null($project)) {
            throw new \Exception('Project not set on invoice.');
        }

        $filterData = new InvoiceEntryWorklogsFilterData();
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class, $filterData);

        $form->add('version', EntityType::class, [
            'class' => Version::class,
            'required' => false,
            'label' => 'worklog.version',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row'],
            'attr' => ['class' => 'form-element'],
            'help' => 'worklog.version_helptext',
            'choices' => $project->getVersions(),
        ]);

        $form->handleRequest($request);

        $worklogs = $worklogRepository->findByFilterData($project, $invoiceEntry, $filterData);

        return $this->render('invoice_entry/worklogs.html.twig', [
            'form' => $form,
            'invoice' => $invoice,
            'invoiceEntry' => $invoiceEntry,
            'worklogs' => $worklogs,
        ]);
    }

    #[Route('/{invoiceEntry}/worklogs-show', name: 'app_invoice_entry_worklogs_show', methods: ['GET'])]
    public function showWorklogs(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, WorklogRepository $worklogRepository): Response
    {
        if (InvoiceEntryTypeEnum::WORKLOG != $invoiceEntry->getEntryType()) {
            throw new \Exception('Invoice entry is not a WORKLOG type.');
        }

        $worklogs = $worklogRepository->findBy(['invoiceEntry' => $invoiceEntry]);

        return $this->render('invoice_entry/worklogs_show.html.twig', [
            'invoice' => $invoice,
            'invoiceEntry' => $invoiceEntry,
            'worklogs' => $worklogs,
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/{invoiceEntry}/select_worklogs', name: 'app_invoice_entry_select_worklogs', methods: ['POST'])]
    public function selectWorklogs(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, WorklogRepository $worklogRepository, TranslatorInterface $translator, BillingService $billingService): Response
    {
        $worklogSelections = $request->toArray();

        foreach ($worklogSelections as $worklogSelection) {
            $worklog = $worklogRepository->find($worklogSelection['id']);

            if (!$worklog) {
                throw new \Exception('Worklog not found');
            }

            if ($worklog->isBilled()) {
                return new JsonResponse(['message' => $translator->trans('worklog.error_already_billed')], 400);
            }

            if ($worklogSelection['checked']) {
                if (null !== $worklog->getInvoiceEntry() && $worklog->getInvoiceEntry() !== $invoiceEntry) {
                    return new JsonResponse(['message' => $translator->trans('worklog.error_added_to_other_invoice_entry')], 400);
                }

                $worklog->setInvoiceEntry($invoiceEntry);
            } else {
                if ($worklog->getInvoiceEntry() === $invoiceEntry) {
                    $worklog->setInvoiceEntry(null);
                }
            }

            $worklogRepository->save($worklog, true);
        }

        $billingService->updateInvoiceEntryTotalPrice($invoiceEntry);

        return new Response(null, 200);
    }
}
