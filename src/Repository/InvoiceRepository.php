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
        $parameters = [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_to' => $to->format('Y-m-d H:i:s'),
        ];

        $qb = $this->createQueryBuilder('inv');
        $qb
            ->where('inv.recorded = true')
            ->andWhere($qb->expr()->between('inv.recordedDate', ':date_from', ':date_to'));

        $qb->setParameters($parameters);

        return $qb->getQuery()->getResult();
    }

    public function getFilteredPagination(InvoiceFilterData $invoiceFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('invoice');

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

        if (null !== $invoiceFilterData->noCost) {
            $qb->andWhere('invoice.noCost = :noCost')->setParameter('noCost', $invoiceFilterData->noCost);
        }

        $defaultSortField = $invoiceFilterData->recorded ? 'invoice.recordedDate' : 'invoice.updatedAt';

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => $defaultSortField, 'defaultSortDirection' => 'desc']
        );
    }
}
