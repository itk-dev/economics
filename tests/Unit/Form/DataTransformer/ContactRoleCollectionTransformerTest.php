<?php

namespace App\Tests\Unit\Form\DataTransformer;

use App\Entity\ContactRole;
use App\Form\DataTransformer\ContactRoleCollectionTransformer;
use App\Repository\ContactRoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContactRoleCollectionTransformerTest extends TestCase
{
    private ContactRoleRepository&MockObject $repository;
    private ContactRoleCollectionTransformer $transformer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ContactRoleRepository::class);
        $this->transformer = new ContactRoleCollectionTransformer($this->repository);
    }

    public function testTransformReducesRolesToTheirNames(): void
    {
        $roles = new ArrayCollection([
            $this->role('Fakturering'),
            $this->role('Daglig kontakt'),
        ]);

        $this->assertSame(['Fakturering', 'Daglig kontakt'], $this->transformer->transform($roles));
    }

    public function testTransformAcceptsNull(): void
    {
        $this->assertSame([], $this->transformer->transform(null));
    }

    public function testReverseTransformReusesAnExistingRole(): void
    {
        $existing = $this->role('Fakturering');
        $this->repository->method('findOneByName')->with('Fakturering')->willReturn($existing);

        $roles = $this->transformer->reverseTransform(['Fakturering']);

        $this->assertCount(1, $roles);
        $this->assertSame($existing, $roles->first());
    }

    public function testReverseTransformCreatesAnUnknownRole(): void
    {
        $this->repository->method('findOneByName')->willReturn(null);

        $roles = $this->transformer->reverseTransform(['Teknisk']);

        $this->assertCount(1, $roles);
        $created = $roles->first();
        $this->assertInstanceOf(ContactRole::class, $created);
        $this->assertSame('Teknisk', $created->getName());
        $this->assertNull($created->getId(), 'A created role is left unpersisted for the cascade to pick up.');
    }

    public function testANameRepeatedInOneCallYieldsOneRole(): void
    {
        $this->repository->method('findOneByName')->willReturn(null);

        $roles = $this->transformer->reverseTransform(['Teknisk', 'Teknisk']);

        $this->assertCount(1, $roles);
    }

    public function testTheSameNewNameAcrossTwoCallsYieldsOneRole(): void
    {
        // Two contacts in a single submit each transform their own role list, so
        // the created role must be shared or the unique index would reject it.
        $this->repository->method('findOneByName')->willReturn(null);

        $first = $this->transformer->reverseTransform(['Teknisk']);
        $second = $this->transformer->reverseTransform(['Teknisk']);

        $this->assertSame($first->first(), $second->first());
    }

    public function testACreatedRoleIsMatchedCaseInsensitively(): void
    {
        // The name column collates case-insensitively, so "test" must not create
        // a second row alongside a "Test" created moments earlier.
        $this->repository->method('findOneByName')->willReturn(null);

        $first = $this->transformer->reverseTransform(['Test']);
        $second = $this->transformer->reverseTransform(['test']);

        $this->assertSame($first->first(), $second->first());
    }

    public function testReverseTransformTrimsAndSkipsBlankNames(): void
    {
        $this->repository->method('findOneByName')->with('Fakturering')->willReturn(null);

        $roles = $this->transformer->reverseTransform(['  Fakturering  ', '', '   ']);

        $this->assertCount(1, $roles);
        $created = $roles->first();
        $this->assertInstanceOf(ContactRole::class, $created);
        $this->assertSame('Fakturering', $created->getName());
    }

    public function testReverseTransformAcceptsNull(): void
    {
        $this->assertCount(0, $this->transformer->reverseTransform(null));
    }

    private function role(string $name): ContactRole
    {
        $role = new ContactRole();
        $role->setName($name);

        return $role;
    }
}
