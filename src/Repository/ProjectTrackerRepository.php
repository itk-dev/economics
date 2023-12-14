<?php

namespace App\Repository;

use App\Entity\ProjectTracker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectTracker>
 *
 * @method ProjectTracker|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProjectTracker|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProjectTracker[]    findAll()
 * @method ProjectTracker[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectTrackerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectTracker::class);
    }

//    /**
//     * @return ProjectTracker[] Returns an array of ProjectTracker objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ProjectTracker
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
