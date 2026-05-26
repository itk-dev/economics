<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\ServiceAgreement;
use App\Model\Invoices\ProjectFilterData;
use App\Service\LeantimeUrlGenerator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function getIncluded(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('project');

        $qb
            ->where($qb->expr()->eq('project.include', true))
            ->orderBy('project.name', 'ASC');

        return $qb;
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

        if (!is_null($projectFilterData->isBillable)) {
            $qb->andWhere(
                $projectFilterData->isBillable
                    ? 'project.isBillable = TRUE'
                    : 'project.isBillable = FALSE OR project.isBillable IS NULL'
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

    public function getProjectTrackerIdsByDataProviders(array $dataProviders)
    {
        $qb = $this->createQueryBuilder('project');

        $qb
            ->select('project.projectTrackerId')
            ->where($qb->expr()->eq('project.include', true))
            ->where($qb->expr()->in('project.dataProvider', ':dataProviders'))
            ->setParameter('dataProviders', $dataProviders)
            ->orderBy('project.projectTrackerId', 'ASC');

        return $qb->getQuery()->getSingleColumnResult();
    }

    public function getProjectIdsWithCybersecurityAgreement(): array
    {
        $result = $this->_em->createQueryBuilder()
            ->select('DISTINCT p.id')
            ->from(ServiceAgreement::class, 'sa')
            ->innerJoin('sa.project', 'p')
            ->where('sa.cybersecurityAgreement IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'id');
    }

    /**
     * Returns each project flattened to an array with a derived `leantimeUrl`,
     * its `codeowners` (id/name/email) and its most recent `serviceAgreement`
     * (by id) nested in. If a project has no service agreement, the key is null.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getApiProjects(LeantimeUrlGenerator $leantimeUrl): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('p', 'sa', 'co', 'dp')
            ->leftJoin('p.dataProvider', 'dp')
            ->leftJoin('p.serviceAgreements', 'sa')
            ->leftJoin('p.codeowners', 'co')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $project) use ($leantimeUrl): array {
            $codeowners = array_map(static fn (array $worker): array => [
                'id' => $worker['id'] ?? null,
                'name' => $worker['name'] ?? null,
                'email' => $worker['email'] ?? null,
            ], $project['codeowners'] ?? []);

            $latest = null;
            foreach ($project['serviceAgreements'] ?? [] as $sa) {
                if (null === $latest || ($sa['id'] ?? 0) > ($latest['id'] ?? 0)) {
                    $latest = $sa;
                }
            }

            $url = $leantimeUrl->baseUrl($project['dataProvider']['url'] ?? null);

            unset($project['codeowners'], $project['serviceAgreements'], $project['dataProvider']);

            return [
                ...$project,
                'leantimeUrl' => $url,
                'codeowners' => $codeowners,
                'serviceAgreement' => $latest,
            ];
        }, $results);
    }
}
