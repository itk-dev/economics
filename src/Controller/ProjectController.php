<?php

namespace App\Controller;

use App\Entity\Project;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\ProjectFilterType;
use App\Form\ProjectType;
use App\Model\Invoices\ProjectFilterData;
use App\Repository\ProjectRepository;
use App\Service\DataSynchronizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project')]
#[IsGranted('ROLE_ADMIN')]
class ProjectController extends AbstractController
{
    public function __construct(
    ) {
    }

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

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, ProjectRepository $projectRepository): Response
    {
        $form = $this->createForm(ProjectType::class, $project);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectRepository->save($project, true);
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
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

    #[Route('/{id}/isBillable', name: 'app_project_is_billable', methods: ['POST'])]
    public function isBillable(Request $request, Project $project, ProjectRepository $projectRepository): Response
    {
        $body = $request->toArray();

  if (!empty($body['value'])) {
        $project->setIsBillable($body['value']);
        $projectRepository->save($project, true);
        return new JsonResponse([$body], 200);
    } else {
        throw new BadRequestHttpException('Value not set.');
    }
        $projectRepository->save($project, true);

        return new JsonResponse([$body], 200);
    }

    /**
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    #[Route('/{id}/sync', name: 'app_project_sync', methods: ['POST'])]
    public function sync(Project $project, DataSynchronizationService $dataSynchronizationService): Response
    {
        try {
            $projectId = $project->getId();

            if (null == $projectId) {
                return new Response('Not found', 404);
            }

            $dataProvider = $project->getDataProvider();

            if (null != $dataProvider) {
                $dataSynchronizationService->syncIssuesForProject($projectId, null, $dataProvider);
                $dataSynchronizationService->syncWorklogsForProject($projectId, null, $dataProvider);
            }

            return new JsonResponse([], 200);
        } catch (\Throwable $exception) {
            return new JsonResponse(
                ['message' => $exception->getMessage()],
                (int) ($exception->getCode() > 0 ? $exception->getCode() : 500)
            );
        }
    }
}
