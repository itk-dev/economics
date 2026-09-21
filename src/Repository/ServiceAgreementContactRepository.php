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

    /**
     * The given agreements' contacts, with their roles, keyed by agreement id.
     *
     * One query for a whole page. Rendering the overview off the lazy
     * collections instead costs a query per row to initialise the contacts and
     * another per contact to initialise its roles.
     *
     * The role order is set here rather than left to ORM\OrderBy on the
     * association, which only applies when the collection loads lazily.
     *
     * @param list<int> $serviceAgreementIds
     *
     * @return array<int, list<ServiceAgreementContact>>
     */
    public function findForServiceAgreementsIndexed(array $serviceAgreementIds): array
    {
        if ([] === $serviceAgreementIds) {
            return [];
        }

        /** @var list<ServiceAgreementContact> $contacts */
        $contacts = $this->createQueryBuilder('serviceAgreementContact')
            ->leftJoin('serviceAgreementContact.roles', 'contactRole')->addSelect('contactRole')
            ->andWhere('serviceAgreementContact.serviceAgreement IN (:serviceAgreementIds)')
            ->setParameter('serviceAgreementIds', $serviceAgreementIds)
            ->orderBy('serviceAgreementContact.id', 'ASC')
            ->addOrderBy('contactRole.name', 'ASC')
            ->getQuery()
            ->getResult();

        $indexed = [];

        foreach ($contacts as $contact) {
            // A proxy carries its own id, so reading it adds no query.
            $serviceAgreementId = $contact->getServiceAgreement()?->getId();

            if (null !== $serviceAgreementId) {
                $indexed[$serviceAgreementId][] = $contact;
            }
        }

        return $indexed;
    }
}
