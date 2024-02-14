<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\View;
use App\Form\ViewAddType;
use App\Form\ViewEditType;
use App\Form\ViewSelectType;
use App\Repository\ViewRepository;
use App\Service\ViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/view')]
class ViewController extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ViewService $viewService,
    ) {
    }

    #[Route(['/', '/list'], name: 'app_view_list')]
    public function list(Request $request, ViewRepository $viewRepository): Response
    {
        return $this->render('view/list.html.twig', $this->viewService->addView([
            'views' => $viewRepository->findAll(),
        ]));
    }

    #[Route('/add', name: 'app_view_add')]
    public function add(Request $request, EntityManagerInterface $entityManager): Response
    {
        $view = new View();
        $view->setProtected(false);
        $form = $this->createForm(ViewAddType::class, $view);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($view);
            $entityManager->flush();

            return $this->redirectToRoute('app_view_edit', $this->viewService->addView([
                'id' => $view->getId(),
            ]), Response::HTTP_SEE_OTHER);
        }

        return $this->render('view/add.html.twig', $this->viewService->addView([
            'form' => $form,
        ]));
    }

    #[Route('/{id}/edit', name: 'app_view_edit')]
    public function edit(Request $request, View $view, EntityManagerInterface $entityManager): Response
    {
        $workersOutput = [];
        $workers = $view->getWorkers() ?? [];

        foreach ($workers as $worker) {
            $workersOutput[$worker] = $worker;
        }

        $form = $this->createForm(ViewEditType::class, $view);
        $form->get('workers')->setData($workersOutput);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $workersSelected = $form->get('workers')->getData();
            $view->setWorkers($workersSelected ?? []);

            $dataProviders = $view->getDataProviders();
            foreach ($dataProviders as $dataProvider) {
                $view->addDataProvider($dataProvider);
            }

            $entityManager->persist($view);
            $entityManager->flush();

            return $this->redirectToRoute('app_view_display', $this->viewService->addView([
                'id' => $view->getId(),
            ]), Response::HTTP_SEE_OTHER);
        }

        return $this->render('view/edit.html.twig', $this->viewService->addView([
            'id' => $view->getId(),
            'form' => $form,
        ]));
    }

    #[Route('/{id}/delete', name: 'app_view_delete')]
    public function delete(Request $request, View $view, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$view->getId(), $token)) {
            $entityManager->remove($view);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_view_list', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete/confirm', name: 'app_view_delete_confirm')]
    public function deleteConfirm(Request $request, View $view): Response
    {
        return $this->render('view/viewDelete.html.twig', $this->viewService->addView([
            'view' => $view,
        ]));
    }

    #[Route('/{id}/display', name: 'app_view_display')]
    public function display(Request $request, View $view): Response
    {
        return $this->render('view/display.html.twig', $this->viewService->addView([
            'viewEntity' => $view,
        ]));
    }

    /**
     * Provide select view form. Called from twig: templates/components/navigation.html.twig.
     *
     * @return Response
     */
    public function viewSelector(): Response
    {
        $view = $this->viewService->getCurrentView();

        $form = $this->createForm(ViewSelectType::class, $view);

        /** @var User $user */
        $user = $this->getUser();

        $form
            ->add('viewSelect', EntityType::class, [
                'class' => View::class,
                'label' => false,
                'choice_label' => 'name',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-element',
                    'data-action' => 'view-selector#select',
                ],
                'choices' => $user->getViews(),
                'data' => $view,
            ]);

        return $this->render('view/viewSelect.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Called from twig: templates/components/navigation.html.twig.
     */
    public function getCurrentView(): mixed
    {
        return $this->requestStack->getMainRequest()?->query->get('view') ?? null;
    }

    #[Route('/abandon-view-add', name: 'app_view_add_abandon')]
    public function abandonViewAdd(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('app_view_list', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
    }
}
