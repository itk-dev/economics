<?php

namespace App\Repository;

use App\Entity\Worker;
use App\Entity\WorkerGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Worker>
 *
 * @method Worker|null find($id, $lockMode = null, $lockVersion = null)
 * @method Worker|null findOneBy(array $criteria, array $orderBy = null)
 * @method Worker[]    findAll()
 * @method Worker[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Worker::class);
    }

    public function save(Worker $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Worker $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllIncludedInReports(): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.includeInReports = true')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Worker[]
     */
    public function findIncludedInReportsByGroup(WorkerGroup $group): array
    {
        return $this->createQueryBuilder('w')
            ->innerJoin('w.workerGroups', 'g')
            ->where('w.includeInReports = true')
            ->andWhere('g = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult();
    }
}
