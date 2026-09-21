<?php

namespace App\Repository;

use App\Entity\ContactRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactRole>
 */
class ContactRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactRole::class);
    }

    /**
     * Every role name in use, for the suggestion list on the contact widget.
     *
     * @return string[]
     */
    public function findAllNames(): array
    {
        $names = $this->createQueryBuilder('contactRole')
            ->select('contactRole.name')
            ->orderBy('contactRole.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(fn (mixed $name) => (string) $name, $names);
    }

    /**
     * Matched case-insensitively, so "fakturering" reuses a stored
     * "Fakturering" instead of creating a near-duplicate the unique index would
     * reject.
     *
     * LOWER() in DQL rather than leaning on the column's utf8mb4_unicode_ci
     * collation: the collation is invisible from here, and it is also what the
     * unique index rests on — were it ever to change, this method would quietly
     * start creating the duplicates the index would no longer catch either. The
     * function on the column costs the index, which is nothing on a vocabulary
     * of a handful of rows.
     */
    public function findOneByName(string $name): ?ContactRole
    {
        return $this->createQueryBuilder('contactRole')
            ->andWhere('LOWER(contactRole.name) = LOWER(:name)')
            ->setParameter('name', trim($name))
            ->orderBy('contactRole.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
