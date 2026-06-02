<?php

namespace App\Repository;

use App\Entity\ProjectBilling;
use App\Model\Invoices\ProjectBillingFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<ProjectBilling>
 */
class ProjectBillingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, ProjectBilling::class);
    }

    public function save(ProjectBilling $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProjectBilling $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return PaginationInterface<int, ProjectBilling>
     */
    public function getFilteredPagination(ProjectBillingFilterData $projectBillingFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('pb');

        $qb->andWhere('pb.recorded = :recorded')->setParameter('recorded', $projectBillingFilterData->recorded);

        if (!empty($projectBillingFilterData->createdBy)) {
            $qb->andWhere('pb.createdBy LIKE :createdBy')->setParameter('createdBy', $projectBillingFilterData->createdBy);
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'pb.createdAt', 'defaultSortDirection' => 'desc']
        );
    }
}
