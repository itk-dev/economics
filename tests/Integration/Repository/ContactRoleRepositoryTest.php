<?php

namespace App\Tests\Integration\Repository;

use App\Repository\ContactRoleRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContactRoleRepositoryTest extends KernelTestCase
{
    private ContactRoleRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ContactRoleRepository::class);
    }

    public function testFindAllNamesReturnsTheVocabularySorted(): void
    {
        $names = $this->repository->findAllNames();

        $this->assertContains('Fakturering', $names);
        $this->assertContains('Daglig kontakt', $names);

        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names);
    }

    /**
     * The typed name decides nothing about casing: a role the user spells
     * differently has to resolve to the stored row, or the unique index trips.
     *
     * @dataProvider provideSpellings
     */
    public function testFindOneByNameIgnoresCaseAndSurroundingSpace(string $typed): void
    {
        $role = $this->repository->findOneByName($typed);

        $this->assertNotNull($role);
        $this->assertSame('Fakturering', $role->getName());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideSpellings(): iterable
    {
        yield 'as stored' => ['Fakturering'];
        yield 'lower case' => ['fakturering'];
        yield 'upper case' => ['FAKTURERING'];
        yield 'mixed case' => ['fAkTuReRiNg'];
        yield 'padded' => ['  fakturering  '];
    }

    public function testFindOneByNameReturnsNullForAnUnknownRole(): void
    {
        $this->assertNull($this->repository->findOneByName('Findes ikke'));
    }
}
