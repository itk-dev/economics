<?php

namespace App\Repository;

use App\Entity\Group;
use App\Model\Invoices\NameFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, Group::class);
    }

    public function getFilteredPagination(NameFilterData $nameFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('g');

        if (!is_null($nameFilterData->name)) {
            $name = $nameFilterData->name;
            $qb->andWhere('group.name LIKE :name')->setParameter('name', "%$name%");
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'g.id', 'defaultSortDirection' => 'asc']
        );
    }
}
