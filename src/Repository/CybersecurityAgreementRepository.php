<?php

namespace App\Repository;

use App\Entity\CybersecurityAgreement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\QueryException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CybersecurityAgreement>
 */
class CybersecurityAgreementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CybersecurityAgreement::class);
    }

    /**
     * Retrieves all CybersecurityAgreement entities with array keys indexed by their IDs.
     *
     * @return array<int,CybersecurityAgreement>
     *
     * @throws QueryException
     */
    public function findAllIndexed(): array
    {
        $qb = $this->createQueryBuilder('cybersecurityAgreement');
        $query = $qb->indexBy('cybersecurityAgreement', 'cybersecurityAgreement.id')->getQuery();

        return $query->getResult();
    }
}
