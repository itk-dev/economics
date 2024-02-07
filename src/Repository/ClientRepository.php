<?php

namespace App\Repository;

use App\Entity\Client;
use App\Model\Invoices\ClientFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getFilteredPagination(ClientFilterData $clientFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('client');

        if (!is_null($clientFilterData->name)) {
            $name = $clientFilterData->name;
            $qb->andWhere('client.name LIKE :name')->setParameter('name', "%$name%");
        }

        if (!is_null($clientFilterData->contact)) {
            $contact = $clientFilterData->contact;
            $qb->andWhere('client.contact LIKE :contact')->setParameter('contact', "%$contact%");
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'client.id', 'defaultSortDirection' => 'asc']
        );
    }
}
