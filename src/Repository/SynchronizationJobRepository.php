<?php

namespace App\Repository;

use App\Entity\SynchronizationJob;
use App\Enum\SynchronizationStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SynchronizationJob>
 */
class SynchronizationJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SynchronizationJob::class);
    }

    public function getMessengerMessage(int $id): array|bool
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT * FROM messenger_messages m WHERE m.id = :id';

        $resultSet = $conn->executeQuery($sql, ['id' => $id]);

        return $resultSet->fetchAssociative();
    }

    public function getLatestJob(): ?SynchronizationJob
    {
        $qb = $this->createQueryBuilder('j');
        $qb->orderBy('j.id', 'DESC');
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getNextJob(): ?SynchronizationJob
    {
        $qb = $this->createQueryBuilder('j');
        $qb->addSelect('CASE
            WHEN j.started IS NOT NULL AND j.ended IS NULL THEN 1
            WHEN j.started IS NULL AND j.ended IS NULL THEN 2
            WHEN j.started IS NOT NULL AND j.ended IS NOT NULL THEN 3
            ELSE 4
        END AS HIDDEN priority')
            ->orderBy('priority', 'ASC')
            ->addOrderBy('j.ended', 'DESC')
            ->addOrderBy('j.createdAt', 'ASC')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function countFailedJobs(): int
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('COUNT(j.id)')
            ->where('j.status = :status')
            ->setParameter('status', SynchronizationStatusEnum::ERROR);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getCurrentJob(): ?SynchronizationJob
    {
        $qb = $this->createQueryBuilder('j');
        $qb->where('j.started IS NOT NULL')
            ->andWhere('j.ended IS NULL')
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function countQueuedJobs(): int
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('COUNT(j)')
            ->where('j.status = :status')
            ->setParameter('status', SynchronizationStatusEnum::NOT_STARTED);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function save(SynchronizationJob $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SynchronizationJob $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
