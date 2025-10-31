<?php

namespace App\Repository;

use App\Entity\ServiceAgreement;
use App\Model\Invoices\ClientFilterData;
use App\Model\Invoices\ServiceAgreementFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<ServiceAgreement>
 */
class ServiceAgreementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, ServiceAgreement::class);
    }

    public function getFilteredPagination(ServiceAgreementFilterData $serviceAgreementFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('service_agreement');;
        $qb->leftJoin('service_agreement.project', 'project')
            ->leftJoin('service_agreement.client', 'client')
            ->leftJoin('service_agreement.projectLead', 'projectLead')
            ->leftJoin('service_agreement.cybersecurityAgreement', 'cybersecurityAgreement');


        if (!is_null($serviceAgreementFilterData->project)) {
            $project = $serviceAgreementFilterData->project;
            $qb->leftJoin('service_agreement.project', 'project')
                ->andWhere('project.name LIKE :project')
                ->setParameter('project', "%$project%");
        }

        if (!is_null($serviceAgreementFilterData->client)) {
            $client = $serviceAgreementFilterData->client;
            $qb->leftJoin('service_agreement.client', 'client')
                ->andWhere('client.name LIKE :client')
                ->setParameter('client', "%$client%");
        }

        if (!is_null($serviceAgreementFilterData->cybersecurityAgreement)) {
            if ($serviceAgreementFilterData->cybersecurityAgreement === true) {
                $qb->andWhere('service_agreement.cybersecurityAgreement IS NOT NULL');
            } else {
                $qb->andWhere('service_agreement.cybersecurityAgreement IS NULL');
            }
        }

        if (!is_null($serviceAgreementFilterData->hostingProvider)) {
            $qb->andWhere('service_agreement.hostingProvider = :hostingProvider')
                ->setParameter('hostingProvider', $serviceAgreementFilterData->hostingProvider->value);
        }

        if (!is_null($serviceAgreementFilterData->active)) {
            $qb->andWhere('service_agreement.isActive = :active')
                ->setParameter('active', $serviceAgreementFilterData->active);
        }


        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'service_agreement.id', 'defaultSortDirection' => 'asc']
        );
    }
}
