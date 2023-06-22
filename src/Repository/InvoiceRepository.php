<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Model\Invoices\InvoiceFilterData;
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
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
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

    public function getFilteredPagination(InvoiceFilterData $invoiceFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('invoice');

        $qb->andWhere('invoice.recorded = :recorded')->setParameter('recorded', $invoiceFilterData->recorded);

        if (!empty($invoiceFilterData->createdBy)) {
            $qb->andWhere('invoice.createdBy LIKE :createdBy')->setParameter('createdBy', $invoiceFilterData->createdBy);
        }

        if ($invoiceFilterData->projectBilling) {
            $qb->andWhere('invoice.projectBilling IS NOT NULL');
        } elseif (false === $invoiceFilterData->projectBilling) {
            $qb->andWhere('invoice.projectBilling IS NULL');
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'invoice.createdAt', 'defaultSortDirection' => 'desc']
        );
    }
}
