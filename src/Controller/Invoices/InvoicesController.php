<?php

namespace App\Controller\Invoices;

use App\Entity\Invoice;
use App\Form\Invoices\InvoiceFilterType;
use App\Form\Invoices\InvoiceNewType;
use App\Form\Invoices\InvoiceType;
use App\Model\Invoices\InvoiceFilterData;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Service\BillingService;
use Doctrine\ORM\EntityRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function edit(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository, BillingService $billingService): Response
    {
        $form = $this->createForm(InvoiceType::class, $invoice);

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

            return $this->redirectToRoute('app_invoices_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoices/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
        ]);
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
