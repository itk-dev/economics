<?php

namespace App\Service;

use App\Repository\ViewRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

class ViewService
{
    public function __construct(private readonly RequestStack $requestStack, private readonly ViewRepository $viewRepository)
    {
    }

    public function getCriteria(string $type, QueryBuilder $queryBuilder): Criteria
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $viewId = $currentRequest?->query?->get('viewId');

        if (null != $viewId) {
            $view = $this->viewRepository->find($viewId);

            if (null != $view) {
                $dataProviders = $view->getDataProviders();

                return Criteria::create()->andWhere(
                    Criteria::expr()->in('dataProvider', $dataProviders->toArray())
                );
            }
        }

        return Criteria::create();
    }

    public function addViewIdToRenderArray(array $renderArray): array
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $viewId = $currentRequest?->query?->get('viewId') ?? null;

        if (null != $viewId) {
            return [...$renderArray, 'viewId' => $viewId];
        }

        return $renderArray;
    }
}
