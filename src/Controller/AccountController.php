<?php

namespace App\Controller;

use App\Entity\Account;
use App\Form\AccountFilterType;
use App\Form\AccountType;
use App\Model\Invoices\AccountFilterData;
use App\Repository\AccountRepository;
use App\Service\ViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/account')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
    ) {
    }

    #[Route('/', name: 'app_account_index', methods: ['GET'])]
    public function index(AccountRepository $accountRepository): Response
    {
        $accountFilterData = new AccountFilterData();
        $form = $this->createForm(AccountFilterType::class, $accountFilterData);
        $form->handleRequest($request);

        $pagination = $accountRepository->getFilteredPagination($accountFilterData, $request->query->getInt('page', 1));

        return $this->render('account/index.html.twig', [
            'accounts' => $pagination,
            'form' => $form,
            'viewId' => $request->attributes->get('viewId'),
        ]);
    }

    #[Route('/new', name: 'app_account_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $account = new Account();
        $form = $this->createForm(AccountType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($account);
            $entityManager->flush();

            return $this->redirectToRoute('app_account_index', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
        }

        return $this->render('account/new.html.twig', $this->viewService->addView([
            'account' => $account,
            'form' => $form,
        ]));
    }

    #[Route('/{id}/edit', name: 'app_account_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Account $account, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AccountType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_account_index', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
        }

        return $this->render('account/edit.html.twig', $this->viewService->addView([
            'account' => $account,
            'form' => $form,
        ]));
    }

    #[Route('/{id}', name: 'app_account_delete', methods: ['POST'])]
    public function delete(Request $request, Account $account, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$account->getId(), $token)) {
            $entityManager->remove($account);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_account_index', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
    }
}
