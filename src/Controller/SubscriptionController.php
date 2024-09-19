<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
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
        $userEmail = $user->getEmail();
        $report_type = key($content);
        switch ($report_type) {
            case 'hour_report':
                if (empty($content[$report_type]['dataProvider']) || empty($content[$report_type]['project'])) {
                    return new JsonResponse([], 404);
                }
                // If version is unset, remove it from data
                if (empty($content[$report_type]['version'])) {
                    unset($content[$report_type]['version']);
                }
                // Unset data irrelevant for subscription
                unset(
                    $content[$report_type]['fromDate'],
                    $content[$report_type]['toDate'],
                );

                // If subscriptionType exists, either subscribe or unsubscribe
                $subscriptionType = isset($content[$report_type]['subscriptionType']) ? $content[$report_type]['subscriptionType'] : null;

                if ($subscriptionType) {
                    unset($content[$report_type]['subscriptionType']);

                    return $this->subscriptionHandler($userEmail, $subscriptionType, $content);
                }

                // If no subscriptionType exists, find existing subscriptions and return
                $subscriptions = $this->subscriptionRepository->findBy(['email' => $userEmail, 'urlParams' => json_encode($content)]);

                if ($subscriptions) {
                    $frequencies = $this->getFrequencies($subscriptions);

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

    private function subscriptionHandler($userEmail, $subscriptionType, $content): JsonResponse
    {
        $report_type = key($content);
        $subscription = $this->subscriptionRepository->findOneBy(['email' => $userEmail, 'frequency' => $subscriptionType, 'urlParams' => json_encode($content)]);

        if ($subscription) {
            $this->subscriptionRepository->remove($subscription, true);
            $subscriptions = $this->subscriptionRepository->findBy(['email' => $userEmail, 'urlParams' => json_encode($content)]);
            $frequencies = $this->getFrequencies($subscriptions);

            return new JsonResponse(['success' => true, 'action' => 'unsubscribed', 'frequencies' => $frequencies], 200);
        } else {
            $subscription = new Subscription();
            $subscription->setEmail($userEmail);
            $subscription->setSubject(SubscriptionSubjectEnum::tryFrom($report_type));
            $subscription->setFrequency(SubscriptionFrequencyEnum::tryFrom($subscriptionType));
            $subscription->setUrlParams(json_encode($content));
            $this->subscriptionRepository->save($subscription, true);

            $subscriptions = $this->subscriptionRepository->findBy(['email' => $userEmail, 'urlParams' => json_encode($content)]);
            $frequencies = $this->getFrequencies($subscriptions);

            return new JsonResponse(['success' => true, 'action' => 'subscribed', 'frequencies' => $frequencies], 200);
        }
    }

    private function getFrequencies(array $subscriptions): string
    {
        $frequencies = [];
        foreach ($subscriptions as $subscription) {
            $frequencies[] = $subscription->getFrequency()->value;
        }

        // Implode array with comma to get a pretty string
        return implode(', ', $frequencies);
    }
}
