<?php

namespace App\Repository;

use App\Entity\Version;
use App\Model\Invoices\VersionFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Version>
 *
 * @method Version|null find($id, $lockMode = null, $lockVersion = null)
 * @method Version|null findOneBy(array $criteria, array $orderBy = null)
 * @method Version[]    findAll()
 * @method Version[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, Version::class);
    }

    public function save(Version $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Version $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getFilteredPagination(VersionFilterData $versionFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('version');

        if (!is_null($versionFilterData->isBillable)) {
            $qb->andWhere(
                $versionFilterData->isBillable
                    ? 'version.isBillable = TRUE'
                    : 'version.isBillable = FALSE OR version.isBillable IS NULL'
            );
        }

        if (!is_null($versionFilterData->name)) {
            $name = $versionFilterData->name;
            $qb->andWhere('version.name LIKE :name')->setParameter('name', "%$name%");
        }

        /* if (!is_null($versionFilterData->project)) {
             $project = $versionFilterData->project;
             $qb->andWhere('version.project LIKE :project')->setParameter('project', "%$project%");
         }*/

        return $this->paginator->paginate(
            $qb,
            $page,
            50,
            ['defaultSortFieldName' => 'version.id', 'defaultSortDirection' => 'asc']
        );
    }
}
