<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class HomeController extends AbstractController
{
    public function __construct(
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(DashboardService $dashboardService): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $dashboardData = $dashboardService->getUserDashboard($user);

        return $this->render('home/index.html.twig', [
            'userName' => $user->getName(),
            'dashboardData' => $dashboardData,
        ]);
    }
}
