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

    public function testBackingValuesAreTheAgreedMaterialNumbers(): void
    {
        $this->assertSame(
            ['', '103361', '100006', '100008'],
            array_map(fn (MaterialNumberEnum $case) => $case->value, MaterialNumberEnum::cases())
        );
    }
}
