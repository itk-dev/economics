<?php

namespace App\Controller;

use App\Entity\Issue;
use App\Entity\IssueProduct;
use App\Entity\Project;
use App\Form\IssueFilterType;
use App\Form\IssueProductType;
use App\Model\Invoices\IssueFilterData;
use App\Repository\IssueRepository;
use App\Service\ViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[Route('/admin/project/{project}/issues', name: 'app_issue_')]
#[IsGranted('ROLE_PRODUCT_MANAGER')]
class IssueController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
    ) {
    }

    #[Route('/', name: 'index', methods: [Request::METHOD_GET])]
    public function index(Request $request, Project $project, IssueRepository $issueRepository): Response
    {
        $issueFilterData = new IssueFilterData();
        $issueFilterData->project ??= $project;
        $form = $this->createForm(IssueFilterType::class, $issueFilterData);
        $form->handleRequest($request);

        $pagination = $issueRepository->getFilteredPagination($issueFilterData, $request->query->getInt('page', 1));

        return $this->render('issue/index.html.twig', $this->viewService->addView([
            'project' => $issueFilterData->project,
            'issues' => $pagination,
            'form' => $form,
        ]));
    }

    #[Route('/{id}', name: 'show', methods: [Request::METHOD_GET])]
    public function show(Project $project, Issue $issue): Response
    {
        $product = (new IssueProduct())
            ->setIssue($issue);
        $addProductForm = $this->createForm(IssueProductType::class, $product, [
            'project' => $project,
            'action' => $this->generateUrl('app_issue_add_product', [
                'project' => $project->getId(),
                'id' => $issue->getId(),
            ]),
            'method' => Request::METHOD_POST,
        ]);

        // Index issue products by id.
        $products = [];
        foreach ($issue->getProducts() as $product) {
            if ($id = $product->getId()) {
                $products[$id] = $product;
            }
        }

        $editProductForms = array_map(
            fn (IssueProduct $product) => $this->createForm(IssueProductType::class, $product, [
                'project' => $project,
                'action' => $this->generateUrl('app_issue_edit_product', [
                    'project' => $project->getId(),
                    'id' => $issue->getId(),
                    'product' => $product->getId(),
                ]),
                'method' => Request::METHOD_POST,
            ])
                ->createView(),
            $products
        );

        $productsTotal = array_reduce(
            $products,
            static fn (float $carry, IssueProduct $product) => $product->getTotal() + $carry,
            0.0
        );

        return $this->render('issue/show.html.twig', [
            'project' => $project,
            'issue' => $issue,
            'products_total' => $productsTotal,
            'add_product_form' => $addProductForm,
            'edit_product_forms' => $editProductForms,
        ]);
    }

    #[Route('/{id}/addProduct', name: 'add_product', methods: [Request::METHOD_POST])]
    public function addProduct(Request $request, Project $project, Issue $issue, EntityManagerInterface $entityManager): Response
    {
        $product = (new IssueProduct())
            ->setIssue($issue);
        $form = $this->createForm(IssueProductType::class, $product, [
            'project' => $project,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();
        } else {
            $this->addFlash('danger', new TranslatableMessage('issue.error_adding_product'));
        }

        return $this->redirectToRoute('app_issue_show', [
            'project' => $project->getId(),
            'id' => $issue->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/editProduct/{product}', name: 'edit_product', methods: [Request::METHOD_POST])]
    public function editProduct(Request $request, Project $project, Issue $issue, IssueProduct $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(IssueProductType::class, $product, [
            'project' => $project,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();
        } else {
            $this->addFlash('danger', new TranslatableMessage('issue.error_editing_product'));
        }

        return $this->redirectToRoute('app_issue_show', [
            'project' => $project->getId(),
            'id' => $issue->getId(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/deleteProduct/{product}', name: 'delete_product', methods: [Request::METHOD_DELETE])]
    public function deleteProduct(Request $request, Project $project, Issue $issue, IssueProduct $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_issue_show', [
            'project' => $project->getId(),
            'id' => $issue->getId(),
        ], Response::HTTP_SEE_OTHER);
    }
}
