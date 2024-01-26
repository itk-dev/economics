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

    public function getByRecordedDateBetween(\DateTime $from, \DateTime $to): array
    {
        $qb = $this->createQueryBuilder('inv');

        return $qb
            ->where('inv.recorded = true')
            ->andWhere($qb->expr()->between('inv.recordedDate', ':date_from', ':date_to'))
            ->setParameters([
                'date_from' => $from->format('Y-m-d H:i:s'),
                'date_to' => $to->format('Y-m-d H:i:s'),
            ])
            ->getQuery()
            ->getResult();
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

        $defaultSortField = $invoiceFilterData->recorded ? 'invoice.exportedDate' : 'invoice.updatedAt';

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => $defaultSortField, 'defaultSortDirection' => 'desc']
        );
    }
}
