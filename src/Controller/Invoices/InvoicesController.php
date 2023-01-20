<?php

namespace App\Controller\Invoices;

use App\Entity\Invoice;
use App\Form\Invoices\InvoiceNewType;
use App\Form\Invoices\InvoiceType;
use App\Repository\InvoiceRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/invoices')]
class InvoicesController extends AbstractController
{
    #[Route('/', name: 'app_invoices_index', methods: ['GET'])]
    public function index(Request $request, InvoiceRepository $invoiceRepository, PaginatorInterface $paginator): Response
    {
        $qb = $invoiceRepository->createQueryBuilder('invoice');

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('invoices/index.html.twig', [
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
    public function edit(Request $request, Invoice $invoice, InvoiceRepository $invoiceRepository): Response
    {
        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoiceRepository->save($invoice, true);

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
            $invoiceRepository->remove($invoice, true);
        }

        return $this->redirectToRoute('app_invoices_index', [], Response::HTTP_SEE_OTHER);
    }
}
