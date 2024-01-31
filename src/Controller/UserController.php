<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\RolesEnum;
use App\Repository\UserRepository;
use App\Service\ViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users', )]
class UserController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
    ) {
    }

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', $this->viewService->addView([
            'users' => $userRepository->findAll(),
            'roles' => RolesEnum::cases(),
        ]));
    }

    #[Route('/{id}/update_role', name: 'app_user_update_role', methods: ['POST'])]
    public function updateRole(User $user, Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        $currentUser = $security->getUser();

        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new UnauthorizedHttpException('Only ROLE_ADMIN can edit roles');
        }

        if ($currentUser === $user) {
            throw new BadRequestHttpException('Cannot edit own user.');
        }

        $data = $request->toArray();
        $key = $data['key'];
        $value = $data['value'];

        $roles = $user->getRoles();

        if (true === $value) {
            $roles = array_unique([...$roles, $key]);
        } else {
            $index = array_search($key, $roles);
            if (false !== $index) {
                unset($roles[$index]);
                $roles = array_values($roles);
            }
        }

        $user->setRoles($roles);
        $entityManager->flush();

        return new JsonResponse(['roles' => $user->getRoles()]);
    }
}
