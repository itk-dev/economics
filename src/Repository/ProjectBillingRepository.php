<?php

namespace App\Repository;

use App\Entity\ProjectBilling;
use App\Model\Invoices\InvoiceFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<ProjectBilling>
 *
 * @method ProjectBilling|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProjectBilling|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProjectBilling[]    findAll()
 * @method ProjectBilling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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

    public function getFilteredPagination(int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('pb');

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'pb.createdAt', 'defaultSortDirection' => 'desc']
        );
    }
}
