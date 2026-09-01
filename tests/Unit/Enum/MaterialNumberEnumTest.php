<?php

namespace App\Tests\Unit\Enum;

use App\Enum\MaterialNumberEnum;
use PHPUnit\Framework\TestCase;

class MaterialNumberEnumTest extends TestCase
{
    /**
     * @dataProvider externalityProvider
     */
    public function testIsExternal(MaterialNumberEnum $materialNumber, bool $expected): void
    {
        $this->assertSame($expected, $materialNumber->isExternal());
    }

    /**
     * @return array<string, array{MaterialNumberEnum, bool}>
     */
    public static function externalityProvider(): array
    {
        return [
            'none' => [MaterialNumberEnum::NONE, false],
            'internal' => [MaterialNumberEnum::INTERNAL, false],
            'external with moms' => [MaterialNumberEnum::EXTERNAL_WITH_MOMS, true],
            'external without moms' => [MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS, true],
        ];
    }

    /**
     * @dataProvider materialNumberProvider
     */
    public function testBackingValueIsTheAgreedMaterialNumber(MaterialNumberEnum $case, string $expected): void
    {
        $this->assertSame($expected, $case->value);
    }

    /**
     * @return array<string, array{MaterialNumberEnum, string}>
     */
    public static function materialNumberProvider(): array
    {
        return [
            'none' => [MaterialNumberEnum::NONE, ''],
            'internal' => [MaterialNumberEnum::INTERNAL, '103361'],
            'external with moms' => [MaterialNumberEnum::EXTERNAL_WITH_MOMS, '100006'],
            'external without moms' => [MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS, '100008'],
        ];
    }

    public function testTheProvidersCoverEveryCase(): void
    {
        $this->assertSame(
            array_map(fn (array $row) => $row[0], array_values(self::materialNumberProvider())),
            MaterialNumberEnum::cases()
        );
    }
}
