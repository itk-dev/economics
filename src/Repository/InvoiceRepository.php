<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\View;
use App\Exception\EconomicsException;
use App\Model\Invoices\InvoiceFilterData;
use App\Service\ViewService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @extends ServiceEntityRepository<Invoice>
 *
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method findAll()
 * @method findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator, private readonly ViewService $viewService)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @throws \Doctrine\ORM\Exception\NotSupported
     */
    public function getByRecordedDateBetween(\DateTime $from, \DateTime $to): array
    {
        $viewId = (int) $this->request->getMainRequest()->attributes->get('viewId');
        $qb = $this->createQueryBuilder('inv');

        return $qb
            ->where('inv.recorded = true')
            ->andWhere('inv.project IN (:projectIds)')
            ->andWhere($qb->expr()->between('inv.recordedDate', ':date_from', ':date_to'))
            ->setParameters([
                'date_from' => $from->format('Y-m-d H:i:s'),
                'date_to' => $to->format('Y-m-d H:i:s'),
                'projectIds' => $this->getProjectIdsFromViewId($viewId),
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws EconomicsException
     */
    public function getFilteredPagination(InvoiceFilterData $invoiceFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('invoice');

        $qb = $this->viewService->addWhere($qb, Invoice::class, 'invoice');

        $qb->andWhere('invoice.recorded = :recorded')->setParameter('recorded', $invoiceFilterData->recorded);

        if (!empty($invoiceFilterData->createdBy)) {
            $qb->andWhere('invoice.createdBy LIKE :createdBy')->setParameter('createdBy', $invoiceFilterData->createdBy);
        }

        if ($invoiceFilterData->projectBilling) {
            $qb->andWhere('invoice.projectBilling IS NOT NULL');
        } elseif (false === $invoiceFilterData->projectBilling) {
            $qb->andWhere('invoice.projectBilling IS NULL');
        }

        $defaultSortField = $invoiceFilterData->recorded ? 'invoice.exportedDate' : 'invoice.updatedAt';

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => $defaultSortField, 'defaultSortDirection' => 'desc']
        );
    }

    private function getProjectIdsFromViewId(int $viewId): array
    {
        $view = $this->entityManager->getRepository(View::class)->find($viewId);
        $dataProviders = $view->getDataProviders();

        $dataProviderIds = [];
        foreach ($dataProviders as $dataProvider) {
            $dataProviderIds[] = $dataProvider->getId();
        }

        $projects = $this->entityManager->getRepository(Project::class)->findBy(['dataProvider' => $dataProviderIds]);
        $projectIds = [];
        foreach ($projects as $project) {
            $projectIds[] = $project->getId();
        }

        return $projectIds;
    }
}
