<?php

namespace App\Repository;

use App\Entity\InvoiceEntry;
use App\Entity\Project;
use App\Entity\Worklog;
use App\Model\Invoices\InvoiceEntryWorklogsFilterData;
use App\Service\ViewService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    private const WORKLOGS_PAGINATOR_LIMIT = 50;

    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator, private readonly ViewService $viewService)
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

        if (isset($filterData->worker)) {
            $qb->andWhere('worklog.worker LIKE :worker')->setParameter('worker', '%'.$filterData->worker.'%');
        }

        if (isset($filterData->periodFrom)) {
            $qb->andWhere('worklog.started >= :periodFrom')->setParameter('periodFrom', $filterData->periodFrom);
        }

        if (isset($filterData->periodTo)) {
            $qb->andWhere('worklog.started <= :periodTo')->setParameter('periodTo', $filterData->periodTo);
        }

        if (isset($filterData->version) || isset($filterData->epic)) {
            $qb->leftJoin('App\Entity\Issue', 'issue', 'WITH', 'issue.id = worklog.issue');
        }

        if (isset($filterData->version)) {
            $qb->andWhere(':version MEMBER OF issue.versions')
                ->setParameter('version', $filterData->version);
        }

        if (isset($filterData->epic)) {
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

    public function getTeamReportData(\DateTime $from, \DateTime $to, $viewId, $page): PaginationInterface
    {
        $view = $this->viewService->getViewFromId($viewId);
        $parameters = [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_to' => $to->format('Y-m-d H:i:s'),
        ];

        if (!empty($view)) {
            $dataProviders = $view->getDataProviders()->getValues();
            $projects = $view->getProjects()->getValues();
            $workers = $view->getWorkers();
        }

        $qb = $this->createQueryBuilder('wor');
        $qb->where($qb->expr()->between('wor.started', ':date_from', ':date_to'));
        $qb->leftJoin('wor.project', 'project');
        $qb->leftJoin('wor.issue', 'issue');
        $qb->select('wor', 'project', 'issue');

        if (!empty($projects)) {
            $qb->andWhere('wor.project IN (:projects)');
            $parameters['projects'] = [];
            foreach ($projects as $project) {
                $parameters['projects'][] = $project->getId();
            }
        }

        if (!empty($dataProviders)) {
            $qb->andWhere('wor.dataProvider IN (:dataProviders)');
            $parameters['dataProviders'] = [];
            foreach ($dataProviders as $dataProvider) {
                $parameters['dataProviders'][] = $dataProvider->getId();
            }
        }

        if (!empty($workers)) {
            $qb->andWhere('wor.worker IN (:workers)');
            $parameters['workers'] = $workers;
        }

        $qb->setParameters($parameters);

        return $this->paginator->paginate(
            $qb,
            $page,
            self::WORKLOGS_PAGINATOR_LIMIT,
            ['defaultSortFieldName' => 'wor.started', 'defaultSortDirection' => 'asc']
        );
    }

    public function getDistinctWorklogUsers(): array
    {
        $qb = $this->createQueryBuilder('wor');
        $result = $qb
            ->distinct()
            ->select('wor.worker')
            ->getQuery()->getResult();

        $workers = [];
        foreach ($result as $workLogWorker) {
            $workers[$workLogWorker['worker']] = $workLogWorker['worker'];
        }

        return $workers;
    }
}
