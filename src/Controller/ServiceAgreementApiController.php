<?php

namespace App\Controller;

use App\Repository\ServiceAgreementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceAgreementApiController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'APP_API_KEY')] private readonly string $apiKey,
    ) {
    }

    #[Route('/api/serviceagreements', name: 'app_service_agreement_api', methods: ['GET'])]
    public function index(ServiceAgreementRepository $serviceAgreementRepository, Request $request): Response
    {
        $providedKey = $request->headers->get('X-Api-Key');

        if (!$providedKey) {
            return new JsonResponse(
                ['error' => 'No API key provided'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if ($providedKey !== $this->apiKey) {
            return new JsonResponse(
                ['error' => 'Invalid API key'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'serviceAgreements' => $serviceAgreementRepository->getApiServiceAgreements(),
        ]);
    }
}
