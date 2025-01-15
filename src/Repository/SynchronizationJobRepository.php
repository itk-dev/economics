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

    public function getLatestJob(): ?SynchronizationJob
    {
        $qb = $this->createQueryBuilder('j');
        $qb->orderBy('j.id', 'DESC');
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getIsRunning(): bool
    {
        $qb = $this->createQueryBuilder('j');
        $qb->where(
            $qb->expr()->orX(
                $qb->expr()->eq('j.status', ':running'),
                $qb->expr()->eq('j.status', ':not_started')
            )
        );
        $qb->setParameter('running', SynchronizationStatusEnum::RUNNING->value);
        $qb->setParameter('not_started', SynchronizationStatusEnum::NOT_STARTED->value);

        return count($qb->getQuery()->getArrayResult()) > 0;
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
