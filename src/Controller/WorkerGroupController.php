<?php

namespace App\Controller;

use App\Entity\WorkerGroup;
use App\Form\NameFilterType;
use App\Form\WorkerGroupType;
use App\Model\Invoices\NameFilterData;
use App\Repository\WorkerGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/group')]
#[IsGranted('ROLE_ADMIN')]
class WorkerGroupController extends AbstractController
{
    public function __construct(
    ) {
    }

    #[Route('/', name: 'app_group_index', methods: ['GET'])]
    public function index(Request $request, WorkerGroupRepository $groupRepository): Response
    {
        $groupFilterData = new NameFilterData();
        $form = $this->createForm(NameFilterType::class, $groupFilterData);
        $form->handleRequest($request);

        $pagination = $groupRepository->getFilteredPagination($groupFilterData, $request->query->getInt('page', 1));

        return $this->render('group/index.html.twig', [
            'groups' => $pagination,
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'app_group_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $group = new WorkerGroup();
        $form = $this->createForm(WorkerGroupType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($group);
            $entityManager->flush();

            return $this->redirectToRoute('app_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('group/new.html.twig', [
            'group' => $group,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_group_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, WorkerGroup $group, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(WorkerGroupType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('group/edit.html.twig', [
            'group' => $group,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_group_delete', methods: ['POST'])]
    public function delete(Request $request, WorkerGroup $group, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$group->getId(), $token)) {
            $entityManager->remove($group);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_group_index', [], Response::HTTP_SEE_OTHER);
    }
}
