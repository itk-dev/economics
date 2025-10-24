<?php

namespace App\Controller;

use App\Entity\ServiceAgreement;
use App\Form\ServiceAgreementType;
use App\Repository\ServiceAgreementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/serviceagreements')]
#[IsGranted('ROLE_ADMIN')]
final class ServiceAgreementController extends AbstractController
{
    #[Route(name: 'app_service_agreement_index', methods: ['GET'])]
    public function index(ServiceAgreementRepository $serviceAgreementRepository): Response
    {
        return $this->render('service_agreement/index.html.twig', [
            'service_agreements' => $serviceAgreementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_service_agreement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $serviceAgreement = new ServiceAgreement();
        $form = $this->createForm(ServiceAgreementType::class, $serviceAgreement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($serviceAgreement);
            $entityManager->flush();

            return $this->redirectToRoute('app_service_agreement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('service_agreement/new.html.twig', [
            'service_agreement' => $serviceAgreement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_service_agreement_show', methods: ['GET'])]
    public function show(ServiceAgreement $serviceAgreement): Response
    {
        return $this->render('service_agreement/show.html.twig', [
            'service_agreement' => $serviceAgreement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_service_agreement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ServiceAgreement $serviceAgreement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceAgreementType::class, $serviceAgreement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_service_agreement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('service_agreement/edit.html.twig', [
            'service_agreement' => $serviceAgreement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_service_agreement_delete', methods: ['POST'])]
    public function delete(Request $request, ServiceAgreement $serviceAgreement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$serviceAgreement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($serviceAgreement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_service_agreement_index', [], Response::HTTP_SEE_OTHER);
    }
}
