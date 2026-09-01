<?php

namespace App\Tests\Unit\Doctrine;

use App\Doctrine\Extensions\DBAL\Types\EuropeCopenhagenDateTimeImmutableType;
use App\Doctrine\Extensions\DBAL\Types\EuropeCopenhagenDateTimeType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use PHPUnit\Framework\TestCase;

/**
 * Both DBAL types normalise to Europe/Copenhagen on the way in and out.
 */
class EuropeCopenhagenDateTimeTypesTest extends TestCase
{
    private const FORMAT = 'Y-m-d H:i:s';

    private AbstractPlatform $platform;
    private EuropeCopenhagenDateTimeType $mutableType;
    private EuropeCopenhagenDateTimeImmutableType $immutableType;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(AbstractPlatform::class);
        $this->platform->method('getDateTimeFormatString')->willReturn(self::FORMAT);

        $this->mutableType = new EuropeCopenhagenDateTimeType();
        $this->immutableType = new EuropeCopenhagenDateTimeImmutableType();
    }

    public function testMutableConvertsUtcToCopenhagenOnTheWayToTheDatabase(): void
    {
        $value = new \DateTime('2026-06-01 10:00:00', new \DateTimeZone('UTC'));

        $this->assertSame(
            '2026-06-01 12:00:00',
            $this->mutableType->convertToDatabaseValue($value, $this->platform)
        );
    }

    /**
     * @dataProvider nullValueProvider
     */
    public function testMutableLeavesNullAlone(mixed $value): void
    {
        $this->assertNull($this->mutableType->convertToDatabaseValue($value, $this->platform));
        $this->assertNull($this->mutableType->convertToPHPValue($value, $this->platform));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nullValueProvider(): array
    {
        return ['null' => [null]];
    }

    public function testMutableReadsDatabaseStringsAsCopenhagenTime(): void
    {
        $converted = $this->mutableType->convertToPHPValue('2026-06-01 12:00:00', $this->platform);

        $this->assertInstanceOf(\DateTime::class, $converted);
        $this->assertSame('Europe/Copenhagen', $converted->getTimezone()->getName());
        $this->assertSame('2026-06-01 12:00:00', $converted->format(self::FORMAT));
    }

    public function testMutablePassesThroughExistingDateTimes(): void
    {
        $value = new \DateTime('2026-06-01 12:00:00');

        $this->assertSame($value, $this->mutableType->convertToPHPValue($value, $this->platform));
    }

    public function testMutableRejectsAMalformedDatabaseValue(): void
    {
        $this->expectException(ConversionException::class);

        $this->mutableType->convertToPHPValue('not-a-date', $this->platform);
    }

    public function testImmutableConvertsUtcToCopenhagenOnTheWayToTheDatabase(): void
    {
        $value = new \DateTimeImmutable('2026-06-01 10:00:00', new \DateTimeZone('UTC'));

        $this->assertSame(
            '2026-06-01 12:00:00',
            $this->immutableType->convertToDatabaseValue($value, $this->platform)
        );
    }

    public function testImmutableLeavesCopenhagenValuesUntouched(): void
    {
        $value = new \DateTimeImmutable('2026-06-01 12:00:00', new \DateTimeZone('Europe/Copenhagen'));

        $this->assertSame(
            '2026-06-01 12:00:00',
            $this->immutableType->convertToDatabaseValue($value, $this->platform)
        );
    }

    public function testImmutableDoesNotMutateTheGivenValue(): void
    {
        $value = new \DateTimeImmutable('2026-06-01 10:00:00', new \DateTimeZone('UTC'));

        $this->immutableType->convertToDatabaseValue($value, $this->platform);

        $this->assertSame('UTC', $value->getTimezone()->getName());
        $this->assertSame('2026-06-01 10:00:00', $value->format(self::FORMAT));
    }

    /**
     * @dataProvider nullValueProvider
     */
    public function testImmutableLeavesNullAlone(mixed $value): void
    {
        $this->assertNull($this->immutableType->convertToDatabaseValue($value, $this->platform));
        $this->assertNull($this->immutableType->convertToPHPValue($value, $this->platform));
    }

    public function testImmutableReadsDatabaseStringsAsCopenhagenTime(): void
    {
        $converted = $this->immutableType->convertToPHPValue('2026-06-01 12:00:00', $this->platform);

        $this->assertSame('Europe/Copenhagen', $converted->getTimezone()->getName());
        $this->assertSame('2026-06-01 12:00:00', $converted->format(self::FORMAT));
    }

    public function testImmutablePassesThroughExistingValues(): void
    {
        $value = new \DateTimeImmutable('2026-06-01 12:00:00');

        $this->assertSame($value, $this->immutableType->convertToPHPValue($value, $this->platform));
    }

    public function testImmutableRejectsAMalformedDatabaseValue(): void
    {
        $this->expectException(ConversionException::class);

        $this->immutableType->convertToPHPValue('not-a-date', $this->platform);
    }
}
