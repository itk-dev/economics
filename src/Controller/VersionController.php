<?php

namespace App\Controller;

use App\Entity\Version;
use App\Form\VersionFilterType;
use App\Model\Invoices\VersionFilterData;
use App\Repository\VersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/versions')]
#[IsGranted('ROLE_ADMIN')]
class VersionController extends AbstractController
{
    public function __construct(
    ) {
    }

    #[Route('/', name: 'app_version_index', methods: ['GET'])]
    public function index(Request $request, VersionRepository $versionRepository): Response
    {
        $versionFilterData = new VersionFilterData();
        $form = $this->createForm(VersionFilterType::class, $versionFilterData);
        $form->handleRequest($request);

        $pagination = $versionRepository->getFilteredPagination($versionFilterData, $request->query->getInt('page', 1));

        return $this->render('version/index.html.twig', [
            'versions' => $pagination,
            'form' => $form,
        ]);
    }


    #[Route('/{id}/isBillable', name: 'app_version_is_billable', methods: ['POST'])]
    public function isBillable(Request $request, Version $version, VersionRepository $versionRepository): Response
    {
        $body = $request->toArray();

        if (isset($body['value'])) {
            $version->setIsBillable($body['value']);
            $versionRepository->save($version, true);

            return new JsonResponse([$body], 200);
        } else {
            throw new BadRequestHttpException('Value not set.');
        }
    }

}
