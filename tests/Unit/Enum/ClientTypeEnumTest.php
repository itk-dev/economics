<?php

namespace App\Tests\Unit\Enum;

use App\Enum\ClientTypeEnum;
use App\Enum\MaterialNumberEnum;
use PHPUnit\Framework\TestCase;

class ClientTypeEnumTest extends TestCase
{
    public function testInternalIsNotExternal(): void
    {
        $this->assertFalse(ClientTypeEnum::INTERNAL->isExternal());
    }

    /**
     * @dataProvider externalTypesProvider
     */
    public function testExternalVariantsAreExternal(ClientTypeEnum $type): void
    {
        $this->assertTrue($type->isExternal());
    }

    /**
     * @return array<string, array{ClientTypeEnum}>
     */
    public static function externalTypesProvider(): array
    {
        return [
            'legacy external' => [ClientTypeEnum::EXTERNAL],
            'external with moms' => [ClientTypeEnum::EXTERNAL_WITH_MOMS],
            'external without moms' => [ClientTypeEnum::EXTERNAL_WITHOUT_MOMS],
        ];
    }

    /**
     * @dataProvider materialNumberProvider
     */
    public function testToMaterialNumber(ClientTypeEnum $type, MaterialNumberEnum $expected): void
    {
        $this->assertSame($expected, $type->toMaterialNumber());
    }

    /**
     * @return array<string, array{ClientTypeEnum, MaterialNumberEnum}>
     */
    public static function materialNumberProvider(): array
    {
        return [
            'internal' => [ClientTypeEnum::INTERNAL, MaterialNumberEnum::INTERNAL],
            'with moms' => [ClientTypeEnum::EXTERNAL_WITH_MOMS, MaterialNumberEnum::EXTERNAL_WITH_MOMS],
            'without moms' => [ClientTypeEnum::EXTERNAL_WITHOUT_MOMS, MaterialNumberEnum::EXTERNAL_WITHOUT_MOMS],
            'legacy external defaults to with moms' => [ClientTypeEnum::EXTERNAL, MaterialNumberEnum::EXTERNAL_WITH_MOMS],
        ];
    }
}
