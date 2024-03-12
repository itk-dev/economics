<?php

namespace App\Repository;

use App\Entity\Product;
use App\Model\Invoices\ProductFilterData;
use App\Service\ViewService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PaginatorInterface $paginator,
        private readonly ViewService $viewService
    ) {
        parent::__construct($registry, Product::class);
    }

    public function getFilteredPagination(ProductFilterData $productFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('product');

        if (!is_null($productFilterData->name)) {
            $name = $productFilterData->name;
            $qb->andWhere('product.name LIKE :name')->setParameter('name', "%$name%");
        }

        if (!is_null($productFilterData->project)) {
            $project = $productFilterData->project;
            $qb->andWhere('product.project = :project')->setParameter('project', $project);
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'product.id', 'defaultSortDirection' => 'asc']
        );
    }
}
