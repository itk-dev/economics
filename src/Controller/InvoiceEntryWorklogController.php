<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Version;
use App\Enum\InvoiceEntryTypeEnum;
use App\Exception\EconomicsException;
use App\Form\InvoiceEntryWorklogFilterType;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\IssueRepository;
use App\Repository\WorklogRepository;
use App\Service\BillingService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/invoices/{invoice}/entries')]
#[IsGranted('ROLE_INVOICE')]
class InvoiceEntryWorklogController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws EconomicsException
     */
    #[Route('/{invoiceEntry}/worklogs', name: 'app_invoice_entry_worklogs', methods: ['GET', 'POST'])]
    public function worklogs(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, WorklogRepository $worklogRepository, IssueRepository $issueRepository): Response
    {
        if (InvoiceEntryTypeEnum::WORKLOG != $invoiceEntry->getEntryType()) {
            throw new EconomicsException($this->translator->trans('exception.invoice_entry_not_worklog_type'), 400);
        }

        $project = $invoice->getProject();

        if (is_null($project)) {
            throw new EconomicsException($this->translator->trans('exception.invoice_entry_project_not_set_on_invoice'), 400);
        }

        $filterData = new InvoiceEntryWorklogsFilterData();
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class, $filterData, [
            'invoiceId' => $invoice->getId(),
        ]);

        $form->add('version', EntityType::class, [
            'class' => Version::class,
            'required' => false,
            'label' => 'worklog.version',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row form-choices'],
            'attr' => [
                'class' => 'form-element',
                'data-choices-target' => 'choices',
            ],
            'help' => 'worklog.version_helptext',
            'choices' => $project->getVersions(),
        ]);

        $epics = $issueRepository->findEpicsByProject($project);
        $epicChoices = array_reduce($epics, function ($carry, $item) {
            if (isset($item['epicName']) && isset($item['epicKey'])) {
                $carry[$item['epicName']] = $item['epicKey'];
            }

            return $carry;
        }, []);

        $form->add('epic', ChoiceType::class, [
            'required' => false,
            'label' => 'worklog.epic',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row form-choices'],
            'attr' => [
                'class' => 'form-element',
                'data-choices-target' => 'choices',
            ],
            'help' => 'worklog.epic_helptext',
            'choices' => $epicChoices,
        ]);

        $form->handleRequest($request);

        $worklogs = $worklogRepository->findByFilterData($project, $invoiceEntry, $filterData);

        return $this->render('invoice_entry/worklogs.html.twig', [
            'form' => $form->createView(),
            'invoice' => $invoice,
            'invoiceEntry' => $invoiceEntry,
            'worklogs' => $worklogs,
            'submitEndpoint' => $this->generateUrl('app_invoice_entry_select_worklogs', ['invoice' => $invoice->getId(), 'invoiceEntry' => $invoiceEntry->getId()]),
        ]);
    }

    /**
     * @throws EconomicsException
     */
    #[Route('/{invoiceEntry}/worklogs-show', name: 'app_invoice_entry_worklogs_show', methods: ['GET'])]
    public function showWorklogs(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, WorklogRepository $worklogRepository): Response
    {
        if (InvoiceEntryTypeEnum::WORKLOG != $invoiceEntry->getEntryType()) {
            throw new EconomicsException($this->translator->trans('exception.invoice_entry_not_worklog_type'), 400);
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
    public function selectWorklogs(Request $request, InvoiceEntry $invoiceEntry, WorklogRepository $worklogRepository, BillingService $billingService): Response
    {
        $worklogSelections = $request->toArray();

        foreach ($worklogSelections as $worklogSelection) {
            $worklog = $worklogRepository->find($worklogSelection['id']);

            if (!$worklog) {
                throw new EconomicsException($this->translator->trans('exception.worklog_not_found'), 404);
            }

            if ($worklog->isBilled()) {
                return new JsonResponse(['message' => $this->translator->trans('worklog.error_already_billed')], 400);
            }

            if ($worklogSelection['checked']) {
                if (null !== $worklog->getInvoiceEntry() && $worklog->getInvoiceEntry() !== $invoiceEntry) {
                    return new JsonResponse(['message' => $this->translator->trans('worklog.error_added_to_other_invoice_entry')], 400);
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
