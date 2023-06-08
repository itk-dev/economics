<?php

namespace App\Controller\Invoices;

use App\Entity\ProjectBilling;
use App\Form\Invoices\ProjectBillingType;
use App\Message\CreateProjectBillingMessage;
use App\Message\UpdateProjectBillingMessage;
use App\Repository\ProjectBillingRepository;
use HttpException;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
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
    public function new(Request $request, ProjectBillingRepository $projectBillingRepository, MessageBusInterface $bus): Response
    {
        $projectBilling = new ProjectBilling();
        $projectBilling->setDescription($this->getParameter('app.default_invoice_description') ?? '');

        $form = $this->createForm(ProjectBillingType::class, $projectBilling);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectBilling->setCreatedAt(new \DateTime());
            $projectBilling->setUpdatedAt(new \DateTime());
            $projectBilling->setRecorded(false);
            $projectBillingRepository->save($projectBilling, true);

            // Emit create billing message.
            $bus->dispatch(new CreateProjectBillingMessage($projectBilling->getId()));

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
    public function edit(Request $request, ProjectBilling $projectBilling, ProjectBillingRepository $projectBillingRepository, MessageBusInterface $bus): Response
    {
        $form = $this->createForm(ProjectBillingType::class, $projectBilling);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($projectBilling->isRecorded()) {
                throw new HttpException(400, 'ProjectBilling is recorded, cannot be deleted.');
            }

            $projectBilling->setUpdatedAt(new \DateTime());
            $projectBillingRepository->save($projectBilling, true);

            // Emit create billing message.
            $bus->dispatch(new UpdateProjectBillingMessage($projectBilling->getId()));

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
