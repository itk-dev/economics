<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Subscription>
 *
 * @method Subscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method Subscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method Subscription[]    findAll()
 * @method Subscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PaginatorInterface $paginator)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function save(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param array<string, mixed> $urlParams
     *
     * @return array<int, Subscription>
     */
    public function findByCustom(string $email, array $urlParams): array
    {
        $qb = $this->createQueryBuilder('s');

        $qb->where('s.email = :email')
            ->setParameter('email', $email)
            ->andWhere(
                $qb->expr()->eq(
                    $qb->expr()->lower('s.urlParams'),
                    $qb->expr()->lower(':urlParams')
                )
            )
            ->setParameter('urlParams', json_encode($urlParams));

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $urlParams
     *
     * @throws NonUniqueResultException
     */
    public function findOneByCustom(string $email, string $subscriptionType, array $urlParams): ?Subscription
    {
        $qb = $this->createQueryBuilder('s');

        $qb->where('s.email = :email')
            ->setParameter('email', $email)
            ->andWhere('s.frequency = :subscriptionType')
            ->setParameter('subscriptionType', $subscriptionType)
            ->andWhere(
                $qb->expr()->like(
                    $qb->expr()->lower('s.urlParams'),
                    $qb->expr()->lower(':urlParams')
                )
            )
            ->setParameter('urlParams', json_encode($urlParams))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /*
     * Due to the way searching is implemented in the controller,
     * the repository does not return a paginated element.
     */
    public function getFilteredData(string $email): array
    {
        $qb = $this->createQueryBuilder('subscription')
            ->where('subscription.email = :email')
            ->setParameter('email', $email);

        $results = $qb->getQuery()->getResult();

        return $results;
    }
}
