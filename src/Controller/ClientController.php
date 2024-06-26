<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientFilterType;
use App\Form\ClientType;
use App\Model\Invoices\ClientFilterData;
use App\Repository\ClientRepository;
use App\Service\ClientHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/client')]
#[IsGranted('ROLE_ADMIN')]
class ClientController extends AbstractController
{
    public function __construct(
        private readonly ClientHelper $clientHelper
    ) {
    }

    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $clientRepository): Response
    {
        $clientFilterData = new ClientFilterData();
        $form = $this->createForm(ClientFilterType::class, $clientFilterData);
        $form->handleRequest($request);

        $pagination = $clientRepository->getFilteredPagination($clientFilterData, $request->query->getInt('page', 1));

        return $this->render('client/index.html.twig', [
            'clients' => $pagination,
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ClientHelper $clientHelper): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client, [
            'standard_price' => $clientHelper->getStandardPrice(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($client);
            $entityManager->flush();

            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/new.html.twig');
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('client/show.html.twig');
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClientType::class, $client, [
            'standard_price' => $this->clientHelper->getStandardPrice(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/edit.html.twig');
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('_token');
        if (is_string($token) && $this->isCsrfTokenValid('delete'.$client->getId(), $token)) {
            $entityManager->remove($client);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }
}
