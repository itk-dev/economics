<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\ProtectedDeletableSubscriber;
use App\Exception\DeleteProtectedViewException;
use App\Interface\ProtectedInterface;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProtectedDeletableSubscriberTest extends TestCase
{
    private ProtectedDeletableSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new ProtectedDeletableSubscriber();
    }

    private function createArgs(object $entity): PreRemoveEventArgs
    {
        $em = $this->createMock(EntityManagerInterface::class);

        return new PreRemoveEventArgs($entity, $em);
    }

    public function testProtectedEntityThrowsException(): void
    {
        $entity = $this->createMock(ProtectedInterface::class);
        $entity->method('isProtected')->willReturn(true);

        $this->expectException(DeleteProtectedViewException::class);

        $this->subscriber->preRemove($this->createArgs($entity));
    }

    public function testUnprotectedEntityDoesNotThrow(): void
    {
        $entity = $this->createMock(ProtectedInterface::class);
        $entity->method('isProtected')->willReturn(false);

        // Should not throw
        $this->subscriber->preRemove($this->createArgs($entity));
        $this->addToAssertionCount(1);
    }

    public function testNonProtectedInterfaceEntityDoesNotThrow(): void
    {
        $entity = new \stdClass();

        // Should not throw
        $this->subscriber->preRemove($this->createArgs($entity));
        $this->addToAssertionCount(1);
    }
}
