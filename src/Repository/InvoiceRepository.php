<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Model\Invoices\InvoiceFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

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
    public function __construct(ManagerRegistry $registry)
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

    public function getFilteredQuery(InvoiceFilterData $invoiceFilterData): QueryBuilder
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

        return $qb;
    }
}
