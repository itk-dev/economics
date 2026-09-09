<?php

namespace App\Repository;

use App\Entity\ServiceAgreementContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceAgreementContact>
 */
class ServiceAgreementContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceAgreementContact::class);
    }
}
