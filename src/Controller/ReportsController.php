<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_REPORT')]
class ReportsController extends AbstractController
{
    public function __construct(
    ) {
    }

    #[Route('', name: 'app_reports_index')]
    public function display(): Response
    {
        return $this->render(
            'reports/index.html.twig'
        );
    }
}
