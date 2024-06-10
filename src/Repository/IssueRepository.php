<?php

namespace App\Repository;

use App\Entity\Issue;
use App\Entity\Project;
use App\Model\Invoices\IssueFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

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
    public function __construct(
        ManagerRegistry $registry,
        private readonly PaginatorInterface $paginator
    ) {
        parent::__construct($registry, Issue::class);
    }

    public function save(Issue $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getFilteredPagination(IssueFilterData $issueFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('issue');

        if (!is_null($issueFilterData->name)) {
            $name = $issueFilterData->name;
            $qb->andWhere('issue.name LIKE :name')->setParameter('name', "%$name%");
        }

        if (!is_null($issueFilterData->project)) {
            $project = $issueFilterData->project;
            $qb->andWhere('issue.project = :project')->setParameter('project', $project);
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'issue.id', 'defaultSortDirection' => 'asc']
        );
    }

    public function remove(Issue $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findEpicsByProject(Project $project): array
    {
        $qb = $this->createQueryBuilder('issue');

        $qb->select('issue.epicName, issue.epicKey')
            ->where('issue.project = :project')
            ->setParameter('project', $project)
            ->distinct();

        return $qb->getQuery()->execute();
    }

    public function getClosedIssuesFromInterval(Project $project, \DateTimeInterface $periodStart, \DateTimeInterface $periodEnd)
    {
        $from = new \DateTime($periodStart->format('Y-m-d').' 00:00:00');
        $to = new \DateTime($periodEnd->format('Y-m-d').' 23:59:59');

        $qb = $this->createQueryBuilder('issue');
        $qb->andWhere($qb->expr()->eq('issue.project', ':project'));
        $qb->setParameter('project', $project);
        $qb->andWhere('issue.resolutionDate >= :periodStart');
        $qb->andWhere('issue.resolutionDate <= :periodEnd');
        $qb->setParameter('periodStart', $from);
        $qb->setParameter('periodEnd', $to);
        $qb->andWhere('issue.status IN (:statuses)');
        $qb->setParameter('statuses', $this->getClosedStatuses($project));

        return $qb->getQuery()->execute();
    }

    /**
     * Get "closed" statuses for a project.
     *
     * @return string[]|array
     */
    private function getClosedStatuses(Project $project): array
    {
        // @TODO Get this from project somehow.
        return [
            'Lukket',
            '0',
        ];
    }

    public function issuesContainingVersion(int $versionId): array
    {
        $qb = $this->createQueryBuilder('issue')
            ->where(':version MEMBER OF issue.versions')
            ->setParameter('version', $versionId);

        return $qb->getQuery()->getResult();
    }
}
