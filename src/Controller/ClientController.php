<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Service\ViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/client')]
class ClientController extends AbstractController
{
    public function __construct(
        private readonly ViewService $viewService,
    ) {
    }

    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $clientRepository): Response
    {
        return $this->render('client/index.html.twig', $this->viewService->addView([
            'clients' => $clientRepository->findAll(),
        ]));
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($client);
            $entityManager->flush();

            return $this->redirectToRoute('app_client_index', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/new.html.twig', $this->viewService->addView([
            'client' => $client,
            'form' => $form,
        ]));
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(Request $request, Client $client): Response
    {
        return $this->render('client/show.html.twig', $this->viewService->addView([
            'client' => $client,
        ]));
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_client_index', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/edit.html.twig', $this->viewService->addView([
            'client' => $client,
            'form' => $form,
        ]));
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$client->getId(), $token)) {
            $entityManager->remove($client);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_client_index', $this->viewService->addView([]), Response::HTTP_SEE_OTHER);
    }
}
