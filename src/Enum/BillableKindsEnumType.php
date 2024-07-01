<?php

namespace App\Enum;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class BillableKindsEnumType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return "ENUM('GENERAL_BILLABLE', 'PROJECTMANAGEMENT', 'DEVELOPMENT', 'TESTING')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return BillableKindsEnum::from($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return null;  // Or return a default value if required
        }

        if ($value instanceof BillableKindsEnum) {
            return $value->value;
        }

        throw new \InvalidArgumentException('Invalid value for BillableKindsEnum');
    }

    public function getName(): string
    {
        return 'BillableKindsEnum';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
