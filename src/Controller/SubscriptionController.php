<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use App\Form\SubscriptionFilterType;
use App\Model\Invoices\SubscriptionFilterData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\VersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/subscription')]
#[IsGranted('ROLE_REPORT')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly DataProviderRepository $dataProviderRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly VersionRepository $versionRepository,
    ) {
    }

    #[Route('/', name: 'app_subscription_index', methods: ['GET'])]
    public function index(Request $request, SubscriptionRepository $subscriptionRepository, UserInterface $user): Response
    {
        $subscriptionFilterData = new SubscriptionFilterData();
        $form = $this->createForm(SubscriptionFilterType::class, $subscriptionFilterData);
        $form->handleRequest($request);
        $email = $user->getUserIdentifier();
        $filteredData = $subscriptionRepository->getFilteredData($email);

        $filteredItems = array_filter(
            $filteredData,
            function ($subscription) use ($subscriptionFilterData) {
                return $this->subscriptionFilterHandler($subscription, $subscriptionFilterData);
            }
        );

        return $this->render('subscription/index.html.twig', [
            'subscriptions' => $filteredItems,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_subscription_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, Subscription $subscription, EntityManagerInterface $entityManager): Response
    {
        $subscriptionId = $subscription->getId();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if ($subscription) {
            $entityManager->remove($subscription);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_subscription_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route('/{id}/check', name: 'app_subscription_check', methods: ['POST'])]
    public function check(User $user, Request $request): Response
    {
        $content = $request->toArray();
        $userEmail = $user->getEmail();
        $reportType = key($content);
        $report = &$content[$reportType];
        switch ($reportType) {
            case 'hour_report':
                if (empty($report['dataProvider']) || empty($report['project'])) {
                    return new JsonResponse([], 404);
                }
                // If version is unset, remove it from data
                if (empty($report['version'])) {
                    unset($report['version']);
                }
                // Unset data irrelevant for subscription
                unset(
                    $report['fromDate'],
                    $report['toDate'],
                );

                // If subscriptionType exists, either subscribe or unsubscribe
                $subscriptionType = $report['subscriptionType'] ?? null;

                if ($subscriptionType) {
                    unset($report['subscriptionType']);

                    return $this->subscriptionHandler($userEmail, $subscriptionType, $content);
                }

                // If no subscriptionType exists, find existing subscriptions and return
                $subscriptions = $this->subscriptionRepository->findByCustom($userEmail, $content);

                if ($subscriptions) {
                    $frequencies = $this->getFrequencies($subscriptions);

                    return new JsonResponse(['frequencies' => $frequencies], 200);
                } else {
                    return new JsonResponse([], 200);
                }
                // no break
            default:
                return new JsonResponse(
                    ['error' => 'Unsupported report type'],
                    Response::HTTP_BAD_REQUEST
                );
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    private function subscriptionHandler($userEmail, $subscriptionType, $content): JsonResponse
    {
        $reportType = key($content);
        $subscription = $this->subscriptionRepository->findOneByCustom($userEmail, $subscriptionType, $content);

        if ($subscription) {
            $this->subscriptionRepository->remove($subscription, true);
            $subscriptions = $this->subscriptionRepository->findByCustom($userEmail, $content);
            $frequencies = $this->getFrequencies($subscriptions);

            return new JsonResponse(['action' => 'unsubscribed', 'frequencies' => $frequencies], 200);
        } else {
            $subscription = new Subscription();
            $subscription->setEmail($userEmail);
            $subject = SubscriptionSubjectEnum::tryFrom($reportType);
            $subscription->setSubject($subject ?? throw new \InvalidArgumentException('Invalid subject type: '.$reportType));
            $frequency = SubscriptionFrequencyEnum::tryFrom($subscriptionType);
            $subscription->setFrequency($frequency ?? throw new \InvalidArgumentException('Invalid frequency type: '.$subscriptionType));
            $subscription->setUrlParams($content);
            $this->subscriptionRepository->save($subscription, true);

            $subscriptions = $this->subscriptionRepository->findByCustom($userEmail, $content);
            $frequencies = $this->getFrequencies($subscriptions);

            return new JsonResponse(['action' => 'subscribed', 'frequencies' => $frequencies], 200);
        }
    }

    private function getFrequencies(array $subscriptions): string
    {
        $frequencies = [];
        foreach ($subscriptions as $subscription) {
            $frequencies[] = $subscription->getFrequency()->value;
        }

        // Getting the order from Enum
        $order = array_values(SubscriptionFrequencyEnum::getAsArray());

        // Sort by order of enum definition
        usort($frequencies, function ($a, $b) use ($order) {
            return array_search($a, $order) <=> array_search($b, $order);
        });

        // Implode array with comma to get a pretty string
        return implode(', ', $frequencies);
    }

    private function subscriptionFilterHandler(Subscription $subscription, SubscriptionFilterData $subscriptionFilterData): bool
    {
        $urlParamsArray = $subscription->getUrlParams() ?? [];
        $dataProviderId = $urlParamsArray['hour_report']['dataProvider'];
        $projectId = $urlParamsArray['hour_report']['project'];
        $versionId = $urlParamsArray['hour_report']['version'] ?? null;

        $dataProvider = $this->dataProviderRepository->find($dataProviderId);
        $project = $this->projectRepository->find($projectId);
        $version = $versionId ? $this->versionRepository->find($versionId) : null;

        $urlParams = [
            'dataProvider' => $dataProvider?->getName() ?? '',
            'project' => $project?->getName() ?? '',
            'version' => $version?->getName() ?? '',
        ];

        $subscription->setUrlParams($urlParams);

        if (!isset($subscriptionFilterData->urlParams)) {
            return true;
        }

        $lowercasedFilter = strtolower($subscriptionFilterData->urlParams);

        foreach ($urlParams as $paramValue) {
            if (str_contains(strtolower($paramValue), $lowercasedFilter)) {
                return true;
            }
        }

        return false;
    }
}
