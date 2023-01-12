<?php

namespace App\Controller\SprintReport;

use App\Form\SprintReport\SprintReportType;
use App\Model\SprintReport\SprintReportFormData;
use App\Service\ProjectTracker\ApiServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SprintReportController extends AbstractController
{
    public function __construct(private readonly ApiServiceInterface $apiService)
    {
    }

    #[Route('/sprint-report', name: 'app_sprint_report')]
    public function index(Request $request): Response
    {
        $reportData = null;
        $sprintReportFormData = new SprintReportFormData();

        $form = $this->createForm(SprintReportType::class, $sprintReportFormData);

        $projects = $this->apiService->getAllProjects();

        $projectChoices = [];

        foreach ($projects as $project) {
            $projectChoices[$project->name] = $project->key;
        }

        $form->add('projectId', ChoiceType::class, [
            'placeholder' => 'select an option',
            'choices' => $projectChoices,
            'attr' => [
                'data-sprint-report-target' => 'project'
            ]
        ]);

        $requestData = $request->get('sprint_report');

        if (!empty($requestData["projectId"])) {
            $project = $this->apiService->getProject($requestData["projectId"]);

            $versionChoices = [];
            foreach ($project->versions as $version) {
                $versionChoices[$version->name] = $version->id;
            }

            $form->add('versionId', ChoiceType::class, [
                'placeholder' => 'select an option',
                'choices' => $versionChoices,
                'attr' => [
                    'data-sprint-report-target' => 'version'
                ]
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sprintReportFormData = $form->getData();

            $projectId = $form->get('projectId')->getData();
            $versionId = $form->get('versionId')->getData();

            if (!empty($projectId) && !empty($versionId)) {
                $reportData = $this->apiService->getSprintReportData($projectId, $versionId);
            }
        }

        return $this->render('sprint_report/index.html.twig', [
            'form' => $form->createView(),
            'data' => $sprintReportFormData,
            'report' => $reportData,
        ]);
    }
}
