<?php

namespace App\Controller\Invoices;

use App\Entity\ProjectBilling;
use App\Form\Invoices\ProjectBillingType;
use App\Repository\ProjectBillingRepository;
use HttpException;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/project-billing')]
class ProjectBillingController extends AbstractController
{
    #[Route('/', name: 'app_project_billing_index', methods: ['GET'])]
    public function index(Request $request, ProjectBillingRepository $projectBillingRepository, PaginatorInterface $paginator): Response
    {
        $qb = $projectBillingRepository->createQueryBuilder('project_billing');

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10,
            ['defaultSortFieldName' => 'project_billing.createdAt', 'defaultSortDirection' => 'desc']
        );

        return $this->render('project_billing/index.html.twig', [
            'projectBillings' => $pagination,
        ]);
    }


    #[Route('/new', name: 'app_project_billing_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ProjectBillingRepository $projectBillingRepository): Response
    {
        $projectBilling = new ProjectBilling();
        $form = $this->createForm(ProjectBillingType::class, $projectBilling);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectBilling->setCreatedAt(new \DateTime());
            $projectBilling->setUpdatedAt(new \DateTime());
            $projectBillingRepository->save($projectBilling, true);

            return $this->redirectToRoute('app_project_billing_edit', [
                'id' => $projectBilling->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_billing/new.html.twig', [
            'projectBilling' => $projectBilling,
            'form' => $form,
        ]);
    }

    /**
     * @throws HttpException
     */
    #[Route('/{id}/edit', name: 'app_project_billing_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ProjectBilling $projectBilling, ProjectBillingRepository $projectBillingRepository): Response
    {
        $form = $this->createForm(ProjectBillingType::class, $projectBilling);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($projectBilling->isRecorded()) {
                throw new HttpException(400, 'ProjectBilling is recorded, cannot be deleted.');
            }

            $projectBilling->setUpdatedAt(new \DateTime());
            $projectBillingRepository->save($projectBilling, true);

            return $this->redirectToRoute('app_project_billing_edit', [
                'id' => $projectBilling->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_billing/edit.html.twig', [
            'projectBilling' => $projectBilling,
            'form' => $form,
        ]);
    }

    /**
     * @throws HttpException
     */
    #[Route('/{id}', name: 'app_project_billing_delete', methods: ['POST'])]
    public function delete(Request $request, ProjectBilling $projectBilling, ProjectBillingRepository $projectBillingRepository): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$projectBilling->getId(), $token)) {
            if ($projectBilling->isRecorded()) {
                throw new HttpException(400, 'ProjectBilling is recorded, cannot be deleted.');
            }

            $projectBillingRepository->remove($projectBilling, true);
        }

        return $this->redirectToRoute('app_project_billing_index', [], Response::HTTP_SEE_OTHER);
    }
}
