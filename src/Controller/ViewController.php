<?php

namespace App\Controller;

use App\Entity\View;
use App\Form\ViewAddStepOneType;
use App\Form\ViewAddStepThreeType;
use App\Form\ViewAddStepTwoType;
use App\Form\ViewSelectType;
use App\Repository\ViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/{viewId}/view/')]
class ViewController extends AbstractController
{
    private const VIEW_CREATE_SESSION_KEY = self::class;
    private const CREATEFORM_LAST_STEP = 3;
    private const STEPS = [
        1 => [
            'class' => ViewAddStepOneType::class,
            'template' => 'view/addStepOne.html.twig',
        ],
        2 => [
            'class' => ViewAddStepTwoType::class,
            'template' => 'view/addStepTwo.html.twig',
        ],
        3 => [
            'class' => ViewAddStepThreeType::class,
            'template' => 'view/addStepThree.html.twig',
        ],
    ];

    public function __construct(
        protected RequestStack $requestStack,
        protected ViewRepository $viewRepository
    ) {
    }

    #[Route(['', '/list'], name: 'app_view_list')]
    public function list(Request $request, ViewRepository $viewRepository): Response
    {
        return $this->render('view/list.html.twig', [
            'views' => $viewRepository->findAll(),
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    #[Route('/add', name: 'app_view_add')]
    public function add(Request $request, ViewRepository $viewRepository): Response
    {
        // Create session for multistep form.
        try {
            $session = $request->getSession();
        } catch (SessionNotFoundException $e) {
            $session = new Session();
            $session->start();
        }

        $data = $session->get(self::VIEW_CREATE_SESSION_KEY);

        // Initialize multistep form.
        if (empty($data)) {
            $data = $this->createFormInit();
            $session->set(self::VIEW_CREATE_SESSION_KEY, $data);
        } else {
            // Re-initialize form if it's older than 10 minutes.
            $created = clone $data['created'];
            $created->modify('+10 minutes');
            if ($created < new \DateTime()) {
                $data = $this->createFormInit();
                $session->set(self::VIEW_CREATE_SESSION_KEY, []);
            }
        }

        $form = $this->createForm(self::STEPS[$data['current_step']]['class'], $data['view']);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // If submitting on last step we save the data.
            if (self::CREATEFORM_LAST_STEP === $data['current_step']) {
                if ($form->isValid()) {
                    $session->set(self::VIEW_CREATE_SESSION_KEY, []);

                    $viewRepository->save($data['view'], true);

                    return $this->redirectToRoute('app_view_list', [
                        'id' => $data['view']->getId(),
                        'viewId' => $request->attributes->get('viewId'),
                    ], Response::HTTP_SEE_OTHER);
                }
            } else {
                ++$data['current_step'];
                $session->set(self::VIEW_CREATE_SESSION_KEY, $data);

                return $this->redirectToRoute('app_view_add', [
                    'viewId' => $request->attributes->get('viewId'),
                ], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render(self::STEPS[$data['current_step']]['template'], [
            'form' => $form,
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_view_edit')]
    public function edit(Request $request, View $view, ViewRepository $viewRepository): Response
    {
        return $this->redirectToRoute('app_view_list', ['viewId' => $request->attributes->get('viewId')], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete/confirm', name: 'app_view_delete_confirm')]
    public function deleteConfirm(Request $request, View $view, ViewRepository $viewRepository): Response
    {
        return $this->render('view/viewDelete.html.twig', [
            'view' => $view,
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_view_delete')]
    public function delete(Request $request, View $view, ViewRepository $viewRepository, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$view->getId(), $token)) {
            $entityManager->remove($view);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_view_list', ['viewId' => $request->attributes->get('viewId')], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/display', name: 'app_view_display')]
    public function display(Request $request, View $view, ViewRepository $viewRepository): Response
    {
        return $this->render('view/display.html.twig', [
            'view' => $view,
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    /**
     * Provide select view form.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewSelector(): Response
    {
        $defaultViewId = $this->requestStack->getMainRequest()?->attributes->get('viewId');

        $defaultView = isset($defaultViewId) ? $this->viewRepository->find($defaultViewId) : null;

        $form = $this->createForm(ViewSelectType::class, $defaultView ?? null);

        return $this->render('view/viewSelect.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/abandon-view-add', name: 'app_view_add_abandon')]
    public function abandonViewAdd(Request $request): RedirectResponse
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            $session = new Session();
            $session->start();
        }

        $session->set(self::VIEW_CREATE_SESSION_KEY, []);

        return $this->redirectToRoute('app_view_list', ['viewId' => $request->attributes->get('viewId')], Response::HTTP_SEE_OTHER);
    }

    public function getCurrentView()
    {
        return $this->requestStack->getMainRequest()?->attributes->get('viewId');
    }

    private function createFormInit(): array
    {
        return [
            'view' => new View(),
            'current_step' => 1,
            'created' => new \DateTime(),
        ];
    }
}