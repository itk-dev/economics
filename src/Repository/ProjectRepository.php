<?php

namespace App\Repository;

use App\Entity\Project;
use App\Model\Invoices\ProjectFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Project>
 *
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method findAll()
 * @method findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, Project::class);
    }

    public function save(Project $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Project $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getFilteredPagination(ProjectFilterData $projectFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('project');

        if (!is_null($projectFilterData->include)) {
            $qb->andWhere(
                $projectFilterData->include
                    ? 'project.include = TRUE'
                    : 'project.include = FALSE OR project.include IS NULL'
            );
        }

        if (!is_null($projectFilterData->name)) {
            $name = $projectFilterData->name;
            $qb->andWhere('project.name LIKE :name')->setParameter('name', "%$name%");
        }

        if (!is_null($projectFilterData->key)) {
            $key = $projectFilterData->key;
            $qb->andWhere('project.projectTrackerKey LIKE :key')->setParameter('key', "%$key%");
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'project.id', 'defaultSortDirection' => 'asc']
        );
    }
}
