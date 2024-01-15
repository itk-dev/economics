<?php

namespace App\Repository;

use App\Entity\View;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<View>
 *
 * @method View|null find($id, $lockMode = null, $lockVersion = null)
 * @method View|null findOneBy(array $criteria, array $orderBy = null)
 * @method View[]    findAll()
 * @method View[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, View::class);
    }

    public function save(View $entity, bool $flush = false): void
    {
        // After session storage the referenced entities will no longer persist correctly.
        $this->rehydrateReferencedEntities($entity);
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(View $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * After the entity was stored in session (Step1 - Step3) the Collections
     * collected need to be rehydrated.
     *
     * @param View $entity
     *
     * @return void
     */
    private function rehydrateReferencedEntities(View $entity): void
    {
        foreach ($entity->getReferenceFields() as $referenceFieldType) {
            if ($referenceFieldType instanceof ArrayCollection) {
                foreach ($referenceFieldType as $reference) {
                    $fetchedEntity = $this->getEntityManager()->getRepository(get_class($reference))->find($reference->getId());
                    $entity->removeReference($reference);
                    $entity->addReference($fetchedEntity);
                }
            }
        }
    }
}
