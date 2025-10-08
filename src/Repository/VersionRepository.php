<?php

namespace App\Repository;

use App\Entity\DataProvider;
use App\Entity\Version;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Version>
 *
 * @method Version|null find($id, $lockMode = null, $lockVersion = null)
 * @method Version|null findOneBy(array $criteria, array $orderBy = null)
 * @method Version[]    findAll()
 * @method Version[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Version::class);
    }

    public function save(Version $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Version $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getOldestFetchTime(DataProvider $dataProvider, ?array $projectTrackerProjectIds): ?\DateTimeInterface
    {
        $qb = $this->createQueryBuilder('version');
        $qb->select("version.fetchTime");
        $qb->where($qb->expr()->isNotNull('version.fetchTime'));
        $qb->where('version.dataProvider = :dataProvider');
        $qb->setParameter('dataProvider', $dataProvider);

        if ($projectTrackerProjectIds !== null) {
            $qb->leftJoin('version.project', 'project');
            $qb->andWhere($qb->expr()->in('project.projectTrackerId', $projectTrackerProjectIds));
        }

        $qb->orderBy("version.fetchTime", "ASC");
        $qb->setMaxResults(1);

        $result = $qb->getQuery()->getResult();

        if (count($result) > 0) {
            $result = $result[0];

            return $result['fetchTime'] ?? null;
        }

        return null;
    }
}
