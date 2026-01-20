<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/cybersecurity_report')]
#[IsGranted('ROLE_REPORT')]
final class CybersecurityReportController extends AbstractController
{
    #[Route('/', name: 'app_cybersecurity_report')]
    public function index(): Response
    {
        return $this->render('cybersecurity_report/index.html.twig', [
            'controller_name' => 'CybersecurityReportController',
        ]);
    }
}
