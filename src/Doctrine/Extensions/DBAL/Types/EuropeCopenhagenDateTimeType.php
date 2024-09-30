<?php

namespace App\Doctrine\Extensions\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;

class EuropeCopenhagenDateTimeType extends DateTimeType
{
    private static ?\DateTimeZone $europeCopenhagenTimeZone = null;

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTime) {
            $value->setTimezone(self::getEuropeCopenhagenTimeZone());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTimeInterface
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateTime) {
            return $value;
        }

        $converted = \DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::getEuropeCopenhagenTimeZone()
        );

        if (false === $converted) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), $platform->getDateTimeFormatString());
        }

        return $converted;
    }

    private static function getEuropeCopenhagenTimeZone(): \DateTimeZone
    {
        if (null === self::$europeCopenhagenTimeZone) {
            self::$europeCopenhagenTimeZone = new \DateTimeZone('Europe/Copenhagen');
        }

        return self::$europeCopenhagenTimeZone;
    }
}
