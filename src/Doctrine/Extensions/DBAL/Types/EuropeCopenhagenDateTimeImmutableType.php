<?php

namespace App\Doctrine\Extensions\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeImmutableType;

class EuropeCopenhagenDateTimeImmutableType extends DateTimeImmutableType
{
    private static \DateTimeZone $europeCopenhagenTimeZone;

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeImmutable) {
            if (self::getEuropeCopenhagenTimeZone()->getName() !== $value->getTimezone()->getName()) {
                $mutable = \DateTime::createFromImmutable($value);
                $mutable->setTimezone(self::getEuropeCopenhagenTimeZone());

                $value = \DateTimeImmutable::createFromMutable($mutable);
            }
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $converted = \DateTimeImmutable::createFromFormat(
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
        return self::$europeCopenhagenTimeZone ??= new \DateTimeZone('Europe/Copenhagen');
    }
}
