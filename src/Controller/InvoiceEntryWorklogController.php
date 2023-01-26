<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceEntry;
use App\Entity\Version;
use App\Enum\InvoiceEntryTypeEnum;
use App\Form\InvoiceEntryWorklogFilterType;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Repository\InvoiceEntryRepository;
use App\Repository\WorklogRepository;
use Exception;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/invoices/{invoice}/entries')]
class InvoiceEntryWorklogController extends AbstractController
{
    #[Route('/{invoiceEntry}/worklogs', name: 'app_invoice_entry_worklogs', methods: ['GET', 'POST'])]
    public function worklogs(Request $request, Invoice $invoice, InvoiceEntry $invoiceEntry, WorklogRepository $worklogRepository): Response
    {
        if ($invoiceEntry->getEntryType() != InvoiceEntryTypeEnum::WORKLOG) {
            throw new Exception("Invoice entry is not a WORKLOG type.");
        }

        $project = $invoice->getProject();

        $filterData = new InvoiceEntryWorklogsFilterData();
        $form = $this->createForm(InvoiceEntryWorklogFilterType::class, $filterData);
        $form ->add('version',  EntityType::class, [
            'class' => Version::class,
            'required' => false,
            'label' => 'worklog.version',
            'label_attr' => ['class' => 'label'],
            'row_attr' => ['class' => 'form-row'],
            'attr' => ['class' => 'form-element'],
            'help' => 'worklog.version_helptext',
            'choices' => $invoice->getProject()->getVersions(),
        ]);

        $form->handleRequest($request);

        $worklogs = $worklogRepository->findByFilterData($project, $filterData);

        return $this->render('invoice_entry/worklogs.html.twig', [
            'form' => $form,
            'invoice' => $invoice,
            'invoiceEntry' => $invoiceEntry,
            'worklogs' => $worklogs,
        ]);
    }
}
