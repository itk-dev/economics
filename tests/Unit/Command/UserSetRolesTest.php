<?php

namespace App\Tests\Unit\Command;

use App\Command\UserSetRoles;
use App\Entity\User;
use App\Enum\RolesEnum;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserSetRolesTest extends TestCase
{
    public function testUnknownEmailReturnsFailure(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->method('findOneBy')->with(['email' => 'nobody@test.local'])->willReturn(null);
        $repository->expects($this->never())->method('save');

        $tester = new CommandTester(new UserSetRoles($repository));
        $tester->setInputs(['nobody@test.local']);
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('User not found', $tester->getDisplay());
    }

    /**
     * @dataProvider roleAnswerProvider
     *
     * @param string[] $expectedRoles
     */
    public function testRolesAreDerivedFromTheAnswers(string $roleUser, string $roleAdmin, array $expectedRoles): void
    {
        $user = new User();
        $user->setEmail('user@test.local');

        $repository = $this->createMock(UserRepository::class);
        $repository->method('findOneBy')->with(['email' => 'user@test.local'])->willReturn($user);
        $repository->expects($this->once())->method('save')->with($user, true);

        $tester = new CommandTester(new UserSetRoles($repository));
        $tester->setInputs(['user@test.local', $roleUser, $roleAdmin]);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame($expectedRoles, $user->getRoles());
    }

    /**
     * @return array<string, array{string, string, string[]}>
     */
    public static function roleAnswerProvider(): array
    {
        return [
            'no roles' => ['n', 'n', []],
            'user only' => ['y', 'n', [RolesEnum::ROLE_USER->value]],
            'admin only' => ['n', 'y', [RolesEnum::ROLE_ADMIN->value]],
            'user and admin' => ['y', 'y', [RolesEnum::ROLE_USER->value, RolesEnum::ROLE_ADMIN->value]],
        ];
    }

    public function testExistingRolesAreReplaced(): void
    {
        $user = new User();
        $user->setEmail('user@test.local');
        $user->setRoles([RolesEnum::ROLE_ADMIN->value, RolesEnum::ROLE_INVOICE->value]);

        $repository = $this->createMock(UserRepository::class);
        $repository->method('findOneBy')->willReturn($user);

        $tester = new CommandTester(new UserSetRoles($repository));
        $tester->setInputs(['user@test.local', 'y', 'n']);
        $tester->execute([]);

        $this->assertSame([RolesEnum::ROLE_USER->value], $user->getRoles());
    }
}
