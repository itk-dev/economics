<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user/preferences')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserPreferenceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/hidden-workers', name: 'app_user_preferences_hidden_workers', methods: ['PATCH'])]
    public function setHiddenWorkers(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = json_decode((string) $request->getContent(), true);

        if (!is_array($payload) || !array_key_exists('hiddenWorkers', $payload)) {
            throw new BadRequestHttpException('Body must be JSON {"hiddenWorkers": [...]}.');
        }

        $hiddenWorkers = $payload['hiddenWorkers'];
        if (!is_array($hiddenWorkers)) {
            throw new BadRequestHttpException('"hiddenWorkers" must be an array of strings.');
        }

        $user->setHiddenWorkers(array_values(array_filter($hiddenWorkers, 'is_string')));

        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
