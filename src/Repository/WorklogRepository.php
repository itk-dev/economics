<?php

namespace App\Repository;

use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Enum\NonBillableEpicsEnum;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Worklog>
 *
 * @method Worklog|null find($id, $lockMode = null, $lockVersion = null)
 * @method Worklog|null findOneBy(array $criteria, array $orderBy = null)
 * @method findAll()
 * @method findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorklogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Worklog::class);
    }

    public function save(Worklog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Worklog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByFilterData(Project $project, InvoiceEntry $invoiceEntry, InvoiceEntryWorklogsFilterData $filterData): iterable
    {
        $qb = $this->createQueryBuilder('worklog');

        $qb->where('worklog.project = :project')->setParameter('project', $project);

        if (isset($filterData->isBilled)) {
            $qb->andWhere(
                $filterData->isBilled
                    ? 'worklog.isBilled = TRUE'
                    : 'worklog.isBilled = FALSE OR worklog.isBilled is NULL'
            );
        }

        if (!empty($filterData->worker)) {
            $qb->andWhere('worklog.worker LIKE :worker')->setParameter('worker', '%'.$filterData->worker.'%');
        }

        if (!empty($filterData->periodFrom)) {
            $qb->andWhere('worklog.started >= :periodFrom')->setParameter('periodFrom', $filterData->periodFrom);
        }

        if (!empty($filterData->periodTo)) {
            // Period to must include the selected day.
            $periodTo = $filterData->periodTo->modify('tomorrow');
            $qb->andWhere('worklog.started < :periodTo')->setParameter('periodTo', $periodTo);
        }

        if (!empty($filterData->version) || !empty($filterData->epic)) {
            $qb->leftJoin(Issue::class, 'issue', 'WITH', 'issue.id = worklog.issue');
        }

        if (!empty($filterData->version)) {
            $qb->andWhere(':version MEMBER OF issue.versions')
                ->setParameter('version', $filterData->version);
        }

        if (!empty($filterData->epic)) {
            $qb->andWhere(':epic = issue.epicKey')
                ->setParameter('epic', $filterData->epic);
        }

        if (isset($filterData->onlyAvailable) && $filterData->onlyAvailable) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('worklog.invoiceEntry'),
                $qb->expr()->eq('worklog.invoiceEntry', ':invoiceEntry')
            ))->setParameter('invoiceEntry', $invoiceEntry);
        }

        return $qb->getQuery()->execute();
    }

    public function findWorklogsByWorkerAndDateRange(string $workerIdentifier, \DateTime $dateFrom, \DateTime $dateTo)
    {
        $qb = $this->createQueryBuilder('worklog');

        return $qb
            ->where($qb->expr()->between('worklog.started', ':dateFrom', ':dateTo'))
            ->andWhere('worklog.worker = :worker')
            ->setParameters([
                'worker' => $workerIdentifier,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ])
            ->getQuery()->getResult();
    }

    public function findBillableWorklogsByWorkerAndDateRange(string $workerIdentifier, \DateTime $dateFrom, \DateTime $dateTo)
    {
        $nonBillableEpics = NonBillableEpicsEnum::getAsArray();

        $qb = $this->createQueryBuilder('worklog');

        $qb->leftJoin(Project::class, 'project', 'WITH', 'project.id = worklog.project')
            ->leftJoin('worklog.issue', 'issue')
            ->leftJoin('issue.epics', 'epic');

        return $qb
            ->where($qb->expr()->between('worklog.started', ':dateFrom', ':dateTo'))
            ->andWhere('worklog.worker = :worker')
            ->andWhere($qb->expr()->eq('project.isBillable', '1'))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('epic.title'),
                $qb->expr()->notIn('epic.title', ':nonBillableEpics')
            ))
            ->setParameters([
                'worker' => $workerIdentifier,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'nonBillableEpics' => array_values($nonBillableEpics),
            ])
            ->getQuery()->getResult();
    }

    public function findBilledWorklogsByWorkerAndDateRange(string $workerIdentifier, \DateTime $dateFrom, \DateTime $dateTo)
    {
        $qb = $this->createQueryBuilder('worklog');

        $qb->leftJoin(Project::class, 'project', 'WITH', 'project.id = worklog.project');

        return $qb
            ->where($qb->expr()->between('worklog.started', ':dateFrom', ':dateTo'))
            ->andWhere('worklog.worker = :worker')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('worklog.isBilled', '1'),
            ))
            ->setParameters([
                'worker' => $workerIdentifier,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ])
            ->getQuery()->getResult();
    }

    /**
     * @throws \Exception
     */
    public function getWorklogsAttachedToInvoiceInDateRange(\DateTimeInterface $periodStart, \DateTimeInterface $periodEnd, int $page = 1, int $pageSize = 50): array
    {
        $from = new \DateTimeImmutable($periodStart->format('Y-m-d').' 00:00:00');
        $to = new \DateTimeImmutable($periodEnd->format('Y-m-d').' 23:59:59');

        $query = $this->createQueryBuilder('worklog')
            ->leftJoin(Issue::class, 'issue', 'WITH', 'worklog.issue = issue.id')
            ->leftJoin(Project::class, 'project', 'WITH', 'issue.project = project.id')
            ->where('worklog.invoiceEntry IS NOT NULL')
            ->andWhere('worklog.started BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        $paginator = new Paginator($query, true);

        $totalItemCount = count($paginator);
        $pagesCount = ceil($totalItemCount / $pageSize);

        return [
            'total_count' => $totalItemCount,
            'pages_count' => $pagesCount,
            'current_page' => $page,
            'page_size' => $pageSize,
            'paginator' => $paginator,
        ];
    }
}
