<?php

namespace App\Controller;

use App\Form\ManagementReportDateIntervalType;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/management-report')]
class ManagementReportController extends AbstractController
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    #[Route('', name: 'app_management_reports_create')]
    public function create(Request $request, InvoiceRepository $invoiceRepository): Response
    {
        $recordedInvoicesSorted = $invoiceRepository->findBy(
            ['recorded' => true],
            ['recordedDate' => 'ASC']
        );

        $firstRecordedInvoice = reset($recordedInvoicesSorted);

        $form = $this->createForm(
            ManagementReportDateIntervalType::class,
            ['firstLog' => $firstRecordedInvoice->getRecordedDate()],
            ['action' => $this->container->get('router')->getRouteCollection()->get('app_management_reports_output')->getPath(), 'method' => 'GET']
        );

        return $this->render('management-report/create.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/output', name: 'app_management_reports_output')]
    public function output(Request $request, InvoiceRepository $invoiceRepository): Response
    {
        $queryElements = $request->query->all();
        $dateInterval = $queryElements['management_report_date_interval'] ?? null;

        if (empty($dateInterval['dateFrom']) || empty($dateInterval['dateTo'])) {
            return $this->redirectToRoute('app_management_reports_create', [], Response::HTTP_SEE_OTHER);
        }
        $invoices = $invoiceRepository->getByRecordedDateBetween(
            new \DateTime($dateInterval['dateFrom']),
            new \DateTime($dateInterval['dateTo'].' 23:59:59')
        );

        $groupedInvoices = [];
        foreach ($invoices as $invoice) {
            $recordedDate = $invoice->getRecordedDate();
            $year = $recordedDate->format('Y');
            $month = $recordedDate->format('n');
            $yearQuarter = ceil($month / 3);
            $groupedInvoices[$year][(int) $yearQuarter][] = $invoice;
        }

        foreach ($groupedInvoices as $year => $quarters) {
            // Add empty entries so each quarter is represented.
            $i = 1;
            while ($i <= 4) {
                if (!array_key_exists($i, $groupedInvoices[$year])) {
                    $groupedInvoices[$year][$i] = [];
                }
                ++$i;
            }

            // Add yearly sum.
            $sum = 0;
            foreach ($quarters as $quarter) {
                foreach ($quarter as $invoice) {
                    $sum += $invoice->getTotalPrice();
                }
            }
            $groupedInvoices[$year] = array_merge(['sum' => $sum], $groupedInvoices[$year]);
        }

        return $this->render(
            'management-report/output.html.twig',
            [
                'groupedInvoices' => $groupedInvoices,
                'dateInterval' => $dateInterval,
            ]
        );
    }
}
