<?php

namespace App\Repository;

use App\Entity\SynchronizationJob;
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
