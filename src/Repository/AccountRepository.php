<?php

namespace App\Repository;

use App\Entity\Account;
use App\Model\Invoices\AccountFilterData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Account>
 *
 * @method Account|null find($id, $lockMode = null, $lockVersion = null)
 * @method Account|null findOneBy(array $criteria, array $orderBy = null)
 * @method Account[]    findAll()
 * @method Account[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, Account::class);
    }

    public function save(Account $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Account $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getAllChoices(): array
    {
        $accounts = $this->findAll();

        $accountChoices = array_reduce($accounts, function (array $carry, Account $item) {
            $name = $item->getName();
            $value = $item->getValue();

            $carry["$value: $name"] = $value;

            return $carry;
        }, []);

        return $accountChoices;
    }
    public function getFilteredPagination(AccountFilterData $accountFilterData, int $page = 1): PaginationInterface
    {
        $qb = $this->createQueryBuilder('account');

        if (!is_null($accountFilterData->name)) {
            $name = $accountFilterData->name;
            $qb->andWhere('account.name LIKE :name')->setParameter('name', "%$name%");
        }

        return $this->paginator->paginate(
            $qb,
            $page,
            10,
            ['defaultSortFieldName' => 'account.id', 'defaultSortDirection' => 'asc']
        );
    }
}
