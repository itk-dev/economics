<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Project;
use App\Form\ProductFilterType;
use App\Form\ProductType;
use App\Model\Invoices\ProductFilterData;
use App\Repository\ProductRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/products', name: 'app_product_')]
#[IsGranted('ROLE_PRODUCT_MANAGER')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $productFilterData = new ProductFilterData();
        $productFilterData->project ??= $this->getProject($request);
        $form = $this->createForm(ProductFilterType::class, $productFilterData);
        $form->handleRequest($request);

        $pagination = $productRepository->getFilteredPagination($productFilterData, $request->query->getInt('page', 1));

        return $this->render('product/index.html.twig', [
            'project' => $productFilterData->project,
            'products' => $pagination,
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        if ($project = $this->getProject($request)) {
            $product->setProject($project);
        }
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }

    private function getProject(Request $request): ?Project
    {
        if ($projectId = $request->query->get('project')) {
            return $this->projectRepository->find($projectId);
        }

        return null;
    }
}
