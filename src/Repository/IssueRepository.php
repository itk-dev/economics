<?php

namespace App\Repository;

use App\Entity\Issue;
use App\Entity\Project;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use function Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Issue>
 *
 * @method Issue|null find($id, $lockMode = null, $lockVersion = null)
 * @method Issue|null findOneBy(array $criteria, array $orderBy = null)
 * @method Issue[]    findAll()
 * @method Issue[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Issue::class);
    }

    public function save(Issue $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Issue $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getClosedIssuesFromInterval(Project $project, DateTimeInterface $periodStart, DateTimeInterface $periodEnd)
    {
        $qb = $this->createQueryBuilder('issue');
        $qb->andWhere($qb->expr()->eq('issue.project', $project->getId()));
        $qb->andWhere('issue.resolutionDate >= :periodStart');
        $qb->andWhere('issue.resolutionDate <= :periodEnd');
        $qb->setParameter('periodStart', $periodStart);
        $qb->setParameter('periodEnd', $periodEnd);
        $qb->andWhere('issue.status = :status');
        $qb->setParameter('status', 'Lukket');

        return $qb->getQuery()->execute();
    }
}
