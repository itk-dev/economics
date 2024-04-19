<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Exception\EconomicsException;
use App\Model\Invoices\InvoiceFilterData;
use App\Service\ViewService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Invoice>
 *
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method findAll()
 * @method findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator, private readonly ViewService $viewService)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @throws \Doctrine\ORM\Exception\NotSupported
     */
    public function getByRecordedDateBetween(\DateTime $from, \DateTime $to, $view): array
    {
        $parameters = [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_to' => $to->format('Y-m-d H:i:s'),
        ];

        $qb = $this->createQueryBuilder('inv');
        $qb
            ->where('inv.recorded = true')
            ->andWhere($qb->expr()->between('inv.recordedDate', ':date_from', ':date_to'));

        $projectIds = $this->getProjectIdsFromViewId($view);
        if (!empty($projectIds)) {
            $qb->andWhere('inv.project IN (:projectIds)');
            $parameters['projectIds'] = $projectIds;
        }

        $qb->setParameters($parameters);

        return $qb->getQuery()->getResult();
    }

    /**
     * @throws EconomicsException
     */
    public function getFilteredPagination(InvoiceFilterData $invoiceFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('invoice');

        $qb = $this->viewService->addWhere($qb, Invoice::class, 'invoice');

        $qb->andWhere('invoice.recorded = :recorded')->setParameter('recorded', $invoiceFilterData->recorded);

        $getLikeExpression = static fn (string $value) => str_contains($value, '%') ? $value : '%'.$value.'%';

        if (!empty($invoiceFilterData->query)) {
            $qb->andWhere('invoice.name LIKE :query')->setParameter('query', $getLikeExpression($invoiceFilterData->query));
        }

        if (!empty($invoiceFilterData->createdBy)) {
            $qb->andWhere('invoice.createdBy LIKE :createdBy')->setParameter('createdBy', $getLikeExpression($invoiceFilterData->createdBy));
        }

        if ($invoiceFilterData->projectBilling) {
            $qb->andWhere('invoice.projectBilling IS NOT NULL');
        } elseif (false === $invoiceFilterData->projectBilling) {
            $qb->andWhere('invoice.projectBilling IS NULL');
        }

        $defaultSortField = $invoiceFilterData->recorded ? 'invoice.recordedDate' : 'invoice.updatedAt';

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => $defaultSortField, 'defaultSortDirection' => 'desc']
        );
    }

    private function getProjectIdsFromViewId(string $viewId): array
    {
        $view = $this->viewService->getViewFromId($viewId);

        if (empty($view)) {
            return [];
        }

        $dataProviders = $view->getDataProviders();

        $dataProviderIds = [];
        foreach ($dataProviders as $dataProvider) {
            $dataProviderIds[] = $dataProvider->getId();
        }

        $projects = $this->getEntityManager()->getRepository(Project::class)->findBy(['dataProvider' => $dataProviderIds]);
        $projectIds = [];
        foreach ($projects as $project) {
            $projectIds[] = $project->getId();
        }

        return $projectIds;
    }
}
