<?php

namespace App\Repository;

use App\Entity\DataProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DataProvider>
 *
 * @method DataProvider|null find($id, $lockMode = null, $lockVersion = null)
 * @method DataProvider|null findOneBy(array $criteria, array $orderBy = null)
 * @method DataProvider[]    findAll()
 * @method DataProvider[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DataProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataProvider::class);
    }
}
