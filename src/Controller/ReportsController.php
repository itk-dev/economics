<?php

namespace App\Controller;

use App\Service\ViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reports')]
class ReportsController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
    ) {
    }

    #[Route('', name: 'app_reports_index')]
    public function display(): Response
    {
        return $this->render(
            'reports/index.html.twig',
            $this->viewService->addViewIdToRenderArray([])
        );
    }
}
