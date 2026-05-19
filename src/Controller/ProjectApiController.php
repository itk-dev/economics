<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Service\LeantimeUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectApiController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'APP_API_KEY')] private readonly string $apiKey,
        private readonly LoggerInterface $logger,
        private readonly LeantimeUrlGenerator $leantimeUrlGenerator,
    ) {
    }

    #[Route('/api/projects', name: 'app_project_api', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository, Request $request): Response
    {
        $providedKey = $request->headers->get('X-Api-Key');
        $endpointUrl = $this->generateUrl('app_project_api');

        if (empty($this->apiKey)) {
            $this->logger->error("The endpoint $endpointUrl was called but no API key was defined in env.");

            return new JsonResponse(
                ['error' => 'Service Unavailable'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        if (!$providedKey) {
            $this->logger->error("The endpoint $endpointUrl was called but no API key was provided.");

            return new JsonResponse(
                ['error' => 'No API key provided'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if ($providedKey !== $this->apiKey) {
            $this->logger->error("The endpoint $endpointUrl was called but the provided API key was invalid.");

            return new JsonResponse(
                ['error' => 'Invalid API key'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->json($projectRepository->getApiProjects($this->leantimeUrlGenerator));
    }
}
