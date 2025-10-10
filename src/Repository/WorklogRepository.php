<?php

namespace App\Repository;

use App\Entity\DataProvider;
use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Enum\NonBillableEpicsEnum;
use App\Enum\NonBillableVersionsEnum;
use App\Interface\SynchronizedEntityInterface;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Query\AST\OrderByItem;
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

    /**
     * Gets the sum of time spent grouped by week of year for a specific worker within a range of weeks.
     *
     * @param string $workerEmail The email of the worker
     * @param \DateTimeInterface $from The starting date time
     * @param \DateTimeInterface $to The ending date time
     * @param string $groupBy The function to group by, accepts 'week', 'month' and 'year'
     *
     * @return array An array of results containing total time spent, week number, and worker, indexed by week/month/year number
     */
    public function getTimeSpentByWorkerInWeekRange(
        string $workerEmail,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        string $groupBy,
    ): array {
        $qb = $this->createQueryBuilder('w');

        if ('month' !== $groupBy && 'week' !== $groupBy && 'year' !== $groupBy) {
            throw new \InvalidArgumentException('Invalid group by parameter, function accepts only "week", "month" or "year"');
        }

        $groupByFunction = 'week' === $groupBy ? 'WEEKOFYEAR' : \strtoupper($groupBy);
        $dqlPart = sprintf('%s(w.started) as %s', $groupByFunction, $groupBy);

        $qb
            ->select(
                'SUM(w.timeSpentSeconds) as totalTimeSpent',
                $dqlPart,
                'w.worker'
            )
            ->where('w.worker = :worker')
            ->andWhere('w.started >= :from')
            ->andWhere('w.started <= :to')
            ->setParameter('worker', $workerEmail)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy($groupBy)
            ->orderBy('w.started', 'ASC');

        $results = $qb->getQuery()->getResult();

        // Index results by week/month/year number
        $indexedResults = [];
        foreach ($results as $result) {
            $indexedResults[$result[$groupBy]] = $result;
        }

        return $indexedResults;
    }

    /**
     * Finds billable worklogs within a specific date range for a given worker.
     *
     * @param \DateTime $dateFrom the start date for the date range filter
     * @param \DateTime $dateTo the end date for the date range filter
     * @param string|null $workerIdentifier optional worker identifier to filter worklogs by worker
     * @param mixed|null $isBilled optional indicator for whether the worklog has been billed or not
     *
     * @return array an array of worklogs matching the specified criteria
     */
    public function findBillableWorklogsByWorkerAndDateRange(\DateTime $dateFrom, \DateTime $dateTo, ?string $workerIdentifier = null, mixed $isBilled = null): array
    {
        $nonBillableEpics = NonBillableEpicsEnum::getAsArray();
        $nonBillableVersions = NonBillableVersionsEnum::getAsArray();

        $qb = $this->createQueryBuilder('worklog');

        $qb->leftJoin(Project::class, 'project', 'WITH', 'project.id = worklog.project')
            ->leftJoin('worklog.issue', 'issue')
            ->leftJoin('issue.epics', 'epic')
            ->leftJoin('issue.versions', 'version');

        $qb->where($qb->expr()->between('worklog.started', ':dateFrom', ':dateTo'))
            ->andWhere($qb->expr()->andX(
                $qb->expr()->eq('project.isBillable', '1')
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('epic.title'),
                $qb->expr()->notIn('epic.title', ':nonBillableEpics')
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('version.name'),
                $qb->expr()->notIn('version.name', ':nonBillableVersions')
            ))
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->setParameter('nonBillableEpics', array_values($nonBillableEpics))
            ->setParameter('nonBillableVersions', array_values($nonBillableVersions));

        if (null !== $isBilled) {
            if ($isBilled) {
                $qb->andWhere('worklog.isBilled = :isBilled');
            } else {
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->eq('worklog.isBilled', ':isBilled'),
                        $qb->expr()->isNull('worklog.isBilled')
                    )
                );
            }
            $qb->setParameter('isBilled', $isBilled);
        }

        if (null !== $workerIdentifier) {
            $qb->andWhere('worklog.worker = :worker')
                ->setParameter('worker', $workerIdentifier);
        }

        return $qb->getQuery()->getResult();
    }

    public function findBilledWorklogsByWorkerAndDateRange(string $workerIdentifier, \DateTime $dateFrom, \DateTime $dateTo)
    {
        $nonBillableEpics = NonBillableEpicsEnum::getAsArray();
        $nonBillableVersions = NonBillableVersionsEnum::getAsArray();

        $qb = $this->createQueryBuilder('worklog');

        $qb->leftJoin(Project::class, 'project', 'WITH', 'project.id = worklog.project')
            ->leftJoin('worklog.issue', 'issue')
            ->leftJoin('issue.epics', 'epic')
            ->leftJoin('issue.versions', 'version');

        return $qb
            ->where($qb->expr()->between('worklog.started', ':dateFrom', ':dateTo'))
            ->andWhere('worklog.worker = :worker')
            ->andWhere($qb->expr()->andX(
                $qb->expr()->eq('worklog.isBilled', '1'),
            ))
            // notIn will only work if the string it is checked against is not null
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('epic.title'),
                $qb->expr()->notIn('epic.title', ':nonBillableEpics'),
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('version.name'),
                $qb->expr()->notIn('version.name', ':nonBillableVersions')
            ))
            ->setParameters([
                'worker' => $workerIdentifier,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'nonBillableEpics' => array_values($nonBillableEpics),
                'nonBillableVersions' => array_values($nonBillableVersions),
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
