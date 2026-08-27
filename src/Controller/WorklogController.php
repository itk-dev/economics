<?php

namespace App\Controller;

use App\Form\WorklogFilterType;
use App\Model\Invoices\WorklogFilterData;
use App\Repository\WorklogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/worklog', name: 'app_worklog_')]
#[IsGranted('ROLE_ADMIN')]
class WorklogController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, WorklogRepository $worklogRepository): Response
    {
        $filterData = new WorklogFilterData();
        $form = $this->createForm(WorklogFilterType::class, $filterData);
        $form->handleRequest($request);

        return $this->render('worklog/index.html.twig', [
            'worklogs' => $worklogRepository->getFilteredPagination($filterData, $request->query->getInt('page', 1)),
            'form' => $form,
        ]);
    }
}
