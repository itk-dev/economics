<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/{viewId}/reports/')]
class ReportsController extends AbstractController
{
    #[Route('', name: 'app_reports_index')]
    public function display(Request $request): Response
    {
        return $this->render(
            'reports/index.html.twig',
            ['viewId' => $request->attributes->get('viewId')]
        );
    }
}
