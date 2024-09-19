<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\ProjectFilterType;
use App\Form\ProjectType;
use App\Model\Invoices\ProjectFilterData;
use App\Repository\ProjectRepository;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project')]
#[IsGranted('ROLE_ADMIN')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly subscriptionRepository $subscriptionRepository,
    ) {
    }

    #[Route('/', name: 'app_subscription_index', methods: ['GET'])]
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

    #[Route('/{id}/edit', name: 'app_subscription_edit', methods: ['GET', 'POST'])]
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

    /**
     * @throws EconomicsException
     * @throws UnsupportedDataProviderException
     */
    #[Route('/{id}/check', name: 'app_subscription_check', methods: ['POST'])]
    public function check(User $user, Request $request): Response
    {
        $content = json_decode($request->getContent(), true);
        $report_type = key($content);
        switch ($report_type) {
            case 'hour_report':
                // Unset data irrelevant for subscription
                unset(
                    $content[$report_type]['fromDate'],
                    $content[$report_type]['toDate'],
                    $content[$report_type]['version']
                );

                $subscriptions = $this->subscriptionRepository->findBy(['urlParams' => json_encode($content)]);

                if ($subscriptions) {
                    $frequencies = [];
                    foreach ($subscriptions as $subscription) {
                        $frequencies[] = $subscription->getFrequency()->value;
                    }

                    // Implode array with comma to get a pretty string
                    $frequencies = implode(', ', $frequencies);

                    return new JsonResponse(['success' => true, 'frequencies' => $frequencies], 200);
                } else {
                    return new JsonResponse(['success' => false], 200);
                }

                break;
            default:
                throw new EconomicsException('Unsupported report type: '.$report_type);
                break;
        }
    }
}
