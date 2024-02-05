<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Enum\RolesEnum;
use App\Exception\EconomicsException;
use App\Repository\ViewRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ViewService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ViewRepository $viewRepository,
        private readonly Security $security,
    ) {
    }

    public function addWhere(QueryBuilder $queryBuilder, $entityClass = null, string $alias = null): QueryBuilder
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $viewId = $currentRequest?->query?->get('view');

        $whereSet = false;

        if (null != $viewId) {
            $view = $this->viewRepository->find($viewId);

            if (null != $view) {
                $dataProviders = $view->getDataProviders();

                if (Invoice::class == $entityClass) {
                    $queryBuilder->leftJoin((null !== $alias ? $alias.'.' : '').'project', 'project');
                    $queryBuilder->andWhere($queryBuilder->expr()->in('project.dataProvider', ':dataProviders'));
                } else {
                    $queryBuilder->andWhere($queryBuilder->expr()->in((null !== $alias ? $alias.'.' : '').'dataProvider', ':dataProviders'));
                }

                $queryBuilder->setParameter('dataProviders', $dataProviders);
                $whereSet = true;
            }
        }

        // Required that user has role admin.
        if (!$whereSet) {
            if (!$this->security->isGranted(RolesEnum::ROLE_ADMIN->value)) {
                throw new EconomicsException('Permission denied', 403);
            }
        }

        return $queryBuilder;
    }

    public function addView(array $renderArray): array
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $viewId = $currentRequest?->query?->get('view') ?? null;

        if (null != $viewId) {
            return [...$renderArray, 'view' => $viewId];
        }

        return $renderArray;
    }
}
