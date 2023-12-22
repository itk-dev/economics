<?php

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectFilterType;
use App\Model\Invoices\ProjectFilterData;
use App\Repository\ProjectRepository;
use App\Service\BillingService;
use App\Service\DataProviderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/project')]
class ProjectController extends AbstractController
{
    #[Route('/', name: 'app_project_index', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository): Response
    {
        $projectFilterData = new ProjectFilterData();
        $form = $this->createForm(ProjectFilterType::class, $projectFilterData);
        $form->handleRequest($request);

        $pagination = $projectRepository->getFilteredPagination($projectFilterData, $request->query->getInt('page', 1));

        return $this->render('project/index.html.twig', [
            'projects' => $pagination,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/include', name: 'app_project_include', methods: ['POST'])]
    public function include(Request $request, Project $project, ProjectRepository $projectRepository): Response
    {
        $body = $request->toArray();

        $project->setInclude($body['value']);
        $projectRepository->save($project, true);

        return new JsonResponse([$body], 200);
    }

    /**
     * @throws \Exception
     */
    #[Route('/{id}/sync', name: 'app_project_sync', methods: ['POST'])]
    public function sync(Project $project, DataProviderService $dataProviderService): Response
    {
        $projectId = $project->getId();

        if (null == $projectId) {
            return new Response('Not found', 404);
        }

        $dataProviderService->syncIssuesForProject($projectId);

        $dataProviderService->syncWorklogsForProject($projectId);

        return new JsonResponse([], 200);
    }
}
