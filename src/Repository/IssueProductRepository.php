<?php

namespace App\Repository;

use App\Entity\IssueProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IssueProduct>
 *
 * @method IssueProduct|null find($id, $lockMode = null, $lockVersion = null)
 * @method IssueProduct|null findOneBy(array $criteria, array $orderBy = null)
 * @method IssueProduct[]    findAll()
 * @method IssueProduct[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IssueProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IssueProduct::class);
    }
}
