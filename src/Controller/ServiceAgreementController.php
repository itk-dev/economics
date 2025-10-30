<?php

namespace App\Controller;

use App\Entity\CybersecurityAgreement;
use App\Entity\ServiceAgreement;
use App\Form\CombinedServiceAgreementType;
use App\Repository\CybersecurityAgreementRepository;
use App\Repository\ServiceAgreementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\QueryException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/serviceagreements')]
#[IsGranted('ROLE_ADMIN')]
final class ServiceAgreementController extends AbstractController
{
    /**
     * @throws QueryException
     */
    #[Route(name: 'app_service_agreement_index', methods: ['GET'])]
    public function index(ServiceAgreementRepository $serviceAgreementRepository, CybersecurityAgreementRepository $cybersecurityAgreementRepository): Response
    {
        $serviceAgreements = $serviceAgreementRepository->findAll();
        // Get all cybersecurity agreements indexed by ID
        $cybersecurityAgreements = $cybersecurityAgreementRepository->findAllIndexed();

        return $this->render('service_agreement/index.html.twig', [
            'service_agreements' => $serviceAgreements,
            'cyber_security_agreements' => $cybersecurityAgreements,
        ]);
    }

    #[Route('/new', name: 'app_service_agreement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $serviceAgreement = new ServiceAgreement();
        $cybersecurityAgreement = new CybersecurityAgreement();
        $form = $this->createForm(CombinedServiceAgreementType::class, [
            'serviceAgreement' => $serviceAgreement,
            'cybersecurityAgreement' => $cybersecurityAgreement,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hasCybersecurityAgreement = $request->request->all('combined_service_agreement')['hasCybersecurityAgreement'] ?? false;

            // First persist the ServiceAgreement
            $entityManager->persist($serviceAgreement);
            $entityManager->flush(); // Flush to get the ID

            if ($hasCybersecurityAgreement) {
                // Set up the bidirectional relationship
                $cybersecurityAgreement->setServiceAgreement($serviceAgreement);
                $serviceAgreement->setCybersecurityAgreement($cybersecurityAgreement);

                // Persist CybersecurityAgreement
                $entityManager->persist($cybersecurityAgreement);
                $entityManager->flush();
            }

            return $this->redirectToRoute('app_service_agreement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('service_agreement/new.html.twig', [
            'service_agreement' => $serviceAgreement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_service_agreement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ServiceAgreement $serviceAgreement, EntityManagerInterface $entityManager): Response
    {
        $cybersecurityAgreement = $serviceAgreement->getCybersecurityAgreement() ?? new CybersecurityAgreement();
        // Create the combined form
        $form = $this->createForm(CombinedServiceAgreementType::class, [
            'serviceAgreement' => $serviceAgreement,
            'hasCybersecurityAgreement' => null !== $serviceAgreement->getCybersecurityAgreement(),
            'cybersecurityAgreement' => $cybersecurityAgreement,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hasCybersecurityAgreement = $request->request->all('combined_service_agreement')['hasCybersecurityAgreement'] ?? false;

            if ($hasCybersecurityAgreement) {
                if (!$serviceAgreement->getCybersecurityAgreement()) {
                    // Set up the bidirectional relationship if it doesn't exist
                    $cybersecurityAgreement->setServiceAgreement($serviceAgreement);
                    $serviceAgreement->setCybersecurityAgreement($cybersecurityAgreement);
                    $entityManager->persist($cybersecurityAgreement);
                }
            } else {
                // Remove existing cybersecurity agreement if checkbox is unchecked
                if ($serviceAgreement->getCybersecurityAgreement()) {
                    $entityManager->remove($serviceAgreement->getCybersecurityAgreement());
                    $serviceAgreement->setCybersecurityAgreement(null);
                }
            }

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
