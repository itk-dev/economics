<?php

namespace App\EventListener;

use App\Exception\DeleteProtectedViewException;
use App\Interface\ProtectedInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preRemove, priority: 500, connection: 'default')]
class ProtectedDeletableSubscriber
{
    /**
     * @throws DeleteProtectedViewException
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof ProtectedInterface && $entity->isProtected()) {
            throw new DeleteProtectedViewException();
        }
    }
}
