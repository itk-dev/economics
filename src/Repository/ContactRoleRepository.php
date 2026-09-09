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
     * The name column collates case-insensitively, so this matches "fakturering"
     * against a stored "Fakturering" and keeps the unique index from tripping.
     */
    public function findOneByName(string $name): ?ContactRole
    {
        return $this->findOneBy(['name' => trim($name)]);
    }
}
