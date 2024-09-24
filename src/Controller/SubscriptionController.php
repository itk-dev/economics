<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionFrequencyEnum;
use App\Enum\SubscriptionSubjectEnum;
use App\Exception\EconomicsException;
use App\Exception\UnsupportedDataProviderException;
use App\Form\SubscriptionFilterType;
use App\Model\Invoices\SubscriptionFilterData;
use App\Repository\DataProviderRepository;
use App\Repository\ProjectRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\VersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/subscription')]
#[IsGranted('ROLE_ADMIN')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private readonly subscriptionRepository $subscriptionRepository,
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
            $subject = SubscriptionSubjectEnum::tryFrom($report_type);
            $subscription->setSubject($subject ?? throw new \InvalidArgumentException('Invalid subject type: ' . $report_type));
            $frequency = SubscriptionFrequencyEnum::tryFrom($subscriptionType);
            $subscription->setFrequency($frequency ?? throw new \InvalidArgumentException('Invalid frequency type: ' . $subscriptionType));
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

        // Getting the order from Enum
        $order = array_values(SubscriptionFrequencyEnum::getAsArray());

        // Sort by order of enum definition
        usort($frequencies, function ($a, $b) use ($order) {
            return array_search($a, $order) <=> array_search($b, $order);
        });

        // Implode array with comma to get a pretty string
        return implode(', ', $frequencies);
    }

    /**
     * Handles the subscription filter logic.
     *
     * Is required because search has to be performed in the controller,
     * as data is stored json_encoded and only contains ids.
     *
     * @param Subscription $subscription the Subscription object to apply the filter to
     * @param SubscriptionFilterData $subscriptionFilterData the filter criteria
     *
     * @return bool true if the filter is satisfied, false otherwise
     */
    private function subscriptionFilterHandler(Subscription $subscription, SubscriptionFilterData $subscriptionFilterData): bool
    {
        $urlParamsArray = json_decode($subscription->getUrlParams() ?? '', true);
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

        $subscription->setUrlParams(json_encode($urlParams));

        if (!isset($subscriptionFilterData->urlParams)) {
            return true;
        }

        $lowercasedFilter = strtolower($subscriptionFilterData->urlParams);

        foreach ($urlParams as $paramValue) {
            if (str_contains(strtolower($paramValue), $lowercasedFilter)) {
                $subscription->setUrlParams(json_encode($urlParams));

                return true;
            }
        }

        return false;
    }
}
