<?php

namespace App\Repository;

use App\Entity\InvoiceEntry;
use App\Entity\Issue;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Enum\NonBillableEpicsEnum;
use App\Enum\NonBillableVersionsEnum;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Model\Invoices\WorklogFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

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
    /**
     * Rows held in memory at a time while streaming an export. Raising this trades memory for
     * fewer round trips; the walk cost per chunk is flat either way.
     */
    public const EXPORT_CHUNK_SIZE = 1000;

    public function __construct(
        ManagerRegistry $registry,
        private readonly PaginatorInterface $paginator,
    ) {
        parent::__construct($registry, Worklog::class);
    }

    /**
     * Paginates every worklog in the system, narrowed by the admin worklog page's filter.
     *
     * Unlike findByFilterData() this is not scoped to a project or an invoice entry, so the
     * predicates below have to be watertight on their own — see the parenthesised isBilled
     * expression.
     *
     * @return PaginationInterface<int, Worklog>
     */
    public function getFilteredPagination(WorklogFilterData $filterData, int $page = 1): PaginationInterface
    {
        // The list page renders issue, project and data provider for every row, so fetch-join
        // them here. The export deliberately does not — see streamFilteredForExport().
        $qb = $this->createFilteredQueryBuilder($filterData)
            ->addSelect('issue')
            ->addSelect('project')
            ->addSelect('dataProvider');

        return $this->paginator->paginate($qb, $page, 25, [
            'defaultSortFieldName' => 'worklog.started',
            'defaultSortDirection' => 'desc',
            'sortFieldAllowList' => [
                'worklog.started',
                'worklog.worker',
                'worklog.timeSpentSeconds',
                'worklog.isBilled',
                'issue.name',
                'project.name',
                'dataProvider.name',
            ],
        ]);
    }

    /**
     * Builds the admin worklog filter, without selecting anything beyond the worklog itself.
     *
     * Shared by the paginated list and the CSV export so the two can never drift apart. Callers
     * add their own select: the list fetch-joins entities, the export selects scalars.
     */
    private function createFilteredQueryBuilder(WorklogFilterData $filterData): QueryBuilder
    {
        $qb = $this->createQueryBuilder('worklog')
            ->leftJoin('worklog.issue', 'issue')
            ->leftJoin('worklog.project', 'project')
            ->leftJoin('worklog.dataProvider', 'dataProvider');

        if (!empty($filterData->search)) {
            $qb->andWhere($qb->expr()->orX(
                'worklog.description LIKE :search',
                'issue.name LIKE :search',
                'worklog.projectTrackerIssueId LIKE :search',
            ))->setParameter('search', '%'.$filterData->search.'%');
        }

        if (isset($filterData->isBilled)) {
            // The OR has to be wrapped: DQL binds AND tighter, so an unparenthesised
            // "isBilled = FALSE OR isBilled IS NULL" would widen the whole query.
            $qb->andWhere($filterData->isBilled
                ? $qb->expr()->eq('worklog.isBilled', 'TRUE')
                : $qb->expr()->orX('worklog.isBilled = FALSE', 'worklog.isBilled IS NULL'));
        }

        if (!empty($filterData->periodFrom)) {
            $qb->andWhere('worklog.started >= :periodFrom')->setParameter('periodFrom', $filterData->periodFrom);
        }

        if (!empty($filterData->periodTo)) {
            // Period to must include the selected day. Clone before modifying, so building the
            // query cannot shift the filter the form still holds.
            $periodTo = (clone $filterData->periodTo)->modify('tomorrow');
            $qb->andWhere('worklog.started < :periodTo')->setParameter('periodTo', $periodTo);
        }

        if (!empty($filterData->worker)) {
            // Worklog::$worker is the worker's email, not a relation.
            $qb->andWhere('worklog.worker = :worker')->setParameter('worker', $filterData->worker->getEmail());
        }

        if (!empty($filterData->project)) {
            $qb->andWhere('worklog.project = :project')->setParameter('project', $filterData->project);
        }

        if (!empty($filterData->dataProvider)) {
            $qb->andWhere('worklog.dataProvider = :dataProvider')->setParameter('dataProvider', $filterData->dataProvider);
        }

        return $qb;
    }

    /**
     * Walks every worklog matching the admin worklog filter, for the CSV export.
     *
     * Memory is bounded by $chunkSize rather than by the number of matches, so exporting the whole
     * table costs the same as exporting a page. Two things buy that, and both are load-bearing:
     *
     * - Hydration is scalar, so no entities are created and the identity map never grows. This is
     *   why there is no entityManager->clear() here, unlike ForecastReportService, which has to
     *   clear precisely because it hydrates Worklog objects. It also keeps the walk clear of
     *   Invoice's two eagerly fetched associations, which a hydrated invoiceEntry->invoice would
     *   drag in per row.
     * - Paging is keyset, not offset. LIMIT/OFFSET makes MySQL scan and discard everything before
     *   the offset, so a deep walk degrades quadratically; the (started, id) predicate below is
     *   served by the started_idx index and costs the same on the last chunk as on the first.
     *
     * The order is fixed at started DESC and ignores the list page's sort, which is what makes the
     * keyset walk possible. The filter is honoured exactly.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamFilteredForExport(WorklogFilterData $filterData, int $chunkSize = self::EXPORT_CHUNK_SIZE): \Generator
    {
        // Scalar hydration hands back the raw column string rather than applying the field type,
        // so convert through the mapped type: that keeps the timezone rule in one place and the
        // exported date identical to the one the list page renders.
        $startedType = Type::getType(Types::DATETIME_MUTABLE);
        $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();

        $lastStarted = null;
        $lastId = null;

        do {
            $qb = $this->createFilteredQueryBuilder($filterData)
                ->leftJoin('worklog.invoiceEntry', 'invoiceEntry')
                ->leftJoin('invoiceEntry.invoice', 'invoice')
                ->leftJoin('issue.epics', 'epic')
                ->leftJoin('issue.versions', 'version')
                ->select([
                    'worklog.id AS id',
                    'worklog.started AS started',
                    'worklog.description AS description',
                    'worklog.projectTrackerIssueId AS issueId',
                    'worklog.worker AS worker',
                    'worklog.isBilled AS isBilled',
                    'worklog.timeSpentSeconds AS timeSpentSeconds',
                    'issue.name AS issueName',
                    'project.name AS projectName',
                    'dataProvider.name AS dataProviderName',
                    'invoice.name AS invoiceName',
                    // DISTINCT is required: joining both ManyToManys in one query multiplies the
                    // rows inside each group, so every title would otherwise repeat.
                    "GROUP_CONCAT(DISTINCT epic.title ORDER BY epic.title ASC SEPARATOR ', ') AS epics",
                    "GROUP_CONCAT(DISTINCT version.name ORDER BY version.name ASC SEPARATOR ', ') AS versions",
                ])
                // Group by the primary key of every joined table, not just the worklog: grouping
                // on worklog.id alone while selecting issue.name trips ONLY_FULL_GROUP_BY, whereas
                // the keys let MySQL prove the rest is functionally dependent.
                ->groupBy('worklog.id')
                ->addGroupBy('issue.id')
                ->addGroupBy('project.id')
                ->addGroupBy('dataProvider.id')
                ->addGroupBy('invoiceEntry.id')
                ->addGroupBy('invoice.id')
                ->orderBy('worklog.started', 'DESC')
                ->addOrderBy('worklog.id', 'DESC')
                ->setMaxResults($chunkSize);

            if (null !== $lastStarted) {
                // The orX wrapping is load-bearing for the same reason as the isBilled expression
                // above: DQL binds AND tighter, so an unparenthesised tie-break would widen the
                // whole query instead of narrowing it.
                $qb->andWhere($qb->expr()->orX(
                    'worklog.started < :lastStarted',
                    'worklog.started = :lastStarted AND worklog.id < :lastId',
                ))
                    ->setParameter('lastStarted', $lastStarted)
                    ->setParameter('lastId', $lastId);
            }

            /** @var array<int, array<string, mixed>> $rows */
            $rows = $qb->getQuery()->getScalarResult();
            $fetched = \count($rows);

            foreach ($rows as $row) {
                // Carry the cursor as the raw column value, so it round-trips to the next chunk
                // byte for byte rather than through a timezone conversion.
                $lastStarted = $row['started'];
                $lastId = $row['id'];

                $row['started'] = $startedType->convertToPHPValue($row['started'], $platform);

                yield $row;
            }
        } while ($fetched === $chunkSize);
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
        return $this->createFilterDataQueryBuilder($project, $invoiceEntry, $filterData)
            ->getQuery()
            ->execute();
    }

    /**
     * Sum the time spent on the worklogs matching the given filter that can be
     * added to the invoice entry.
     *
     * Already billed worklogs and worklogs held by another invoice entry are
     * listed without a checkbox, so their time can never become part of the
     * selection and must not be part of the total either.
     */
    public function sumSelectableTimeSpentSecondsByFilterData(Project $project, InvoiceEntry $invoiceEntry, InvoiceEntryWorklogsFilterData $filterData): int
    {
        $qb = $this->createFilterDataQueryBuilder($project, $invoiceEntry, $filterData);

        $sum = $qb
            ->select('SUM(worklog.timeSpentSeconds)')
            ->andWhere('worklog.isBilled = FALSE OR worklog.isBilled is NULL')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('worklog.invoiceEntry'),
                $qb->expr()->eq('worklog.invoiceEntry', ':selectableInvoiceEntry')
            ))
            ->setParameter('selectableInvoiceEntry', $invoiceEntry)
            ->getQuery()
            ->getSingleScalarResult();

        // SUM returns null when no worklogs match the filter.
        return (int) $sum;
    }

    private function createFilterDataQueryBuilder(Project $project, InvoiceEntry $invoiceEntry, InvoiceEntryWorklogsFilterData $filterData): QueryBuilder
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

        if (!empty($filterData->version) || !empty($filterData->epics)) {
            $qb->leftJoin(Issue::class, 'issue', 'WITH', 'issue.id = worklog.issue');
        }

        if (!empty($filterData->version)) {
            $qb->andWhere(':version MEMBER OF issue.versions')
                ->setParameter('version', $filterData->version);
        }

        if (!empty($filterData->epics)) {
            foreach ($filterData->epics as $index => $epic) {
                $qb->andWhere($qb->expr()->isMemberOf(':epic'.$index, 'issue.epics'))
                    ->setParameter('epic'.$index, $epic);
            }
        }

        if (isset($filterData->onlyAvailable) && $filterData->onlyAvailable) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('worklog.invoiceEntry'),
                $qb->expr()->eq('worklog.invoiceEntry', ':invoiceEntry')
            ))->setParameter('invoiceEntry', $invoiceEntry);
        }

        return $qb;
    }

    public function updateProjectByIssue(Issue $issue, Project $project): int
    {
        return $this->createQueryBuilder('w')
            ->update()
            ->set('w.project', ':project')
            ->where('w.issue = :issue')
            ->setParameter('project', $project)
            ->setParameter('issue', $issue)
            ->getQuery()
            ->execute();
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
     * @param string             $workerEmail The email of the worker
     * @param \DateTimeInterface $from        The starting date time
     * @param \DateTimeInterface $to          The ending date time
     * @param string             $groupBy     The function to group by, accepts 'week', 'month' and 'year'
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
     * @param \DateTime   $dateFrom         the start date for the date range filter
     * @param \DateTime   $dateTo           the end date for the date range filter
     * @param string|null $workerIdentifier optional worker identifier to filter worklogs by worker
     * @param mixed|null  $isBilled         optional indicator for whether the worklog has been billed or not
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

    public function anonymizeWorklogs(\DateTimeInterface $anonymizeBefore): int
    {
        $qb = $this->createQueryBuilder('w');

        $qb->update()
            ->set('w.description', 'CONCAT(:prefix, w.id)')
            ->set('w.anonymizedDate', ':now')
            ->where('w.started < :anonymizeBefore')
            ->andWhere('w.anonymizedDate IS NULL')
            ->setParameter('prefix', 'worklog ')
            ->setParameter('now', new \DateTime())
            ->setParameter('anonymizeBefore', $anonymizeBefore);

        return $qb->getQuery()->execute();
    }

    /**
     * Get worklogs for a given issue, optionally restricted by period.
     *
     * @return Worklog[]
     */
    public function getWorklogsByIssueAndPeriod(int $issueId, ?\DateTimeInterface $fromDate, ?\DateTimeInterface $toDate): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.issue = :issue')
            ->setParameter('issue', $issueId);

        if ($fromDate) {
            $qb->andWhere('w.started >= :fromDate')
                ->setParameter('fromDate', $fromDate->format('Y-m-d 00:00:00'));
        }

        if ($toDate) {
            $qb->andWhere('w.started <= :toDate')
                ->setParameter('toDate', $toDate->format('Y-m-d 23:59:59'));
        }

        $qb->orderBy('w.started', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
