<?php

namespace App\Controller\SprintReport;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SprintReportController extends AbstractController
{
    #[Route('/sprint-report', name: 'app_sprint_report')]
    public function index(): Response
    {
        return $this->render('sprint_report/index.html.twig', [
            'controller_name' => 'SprintReportController',
        ]);
    }
}
