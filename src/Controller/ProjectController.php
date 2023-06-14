<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/project')]
class ProjectController extends AbstractController
{
    #[Route('/', name: 'app_project_index', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository, PaginatorInterface $paginator): Response
    {
        $qb = $projectRepository->createQueryBuilder('project');

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10,
            ['defaultSortFieldName' => 'project.id', 'defaultSortDirection' => 'asc']
        );

        return $this->render('project/index.html.twig', [
            'projects' => $pagination,
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
}
