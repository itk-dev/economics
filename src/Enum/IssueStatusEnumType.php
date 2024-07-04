<?php

namespace App\Enum;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class IssueStatusEnumType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return "ENUM('new', 'in progress', 'waiting', 'blocked', 'done', 'archived')";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return IssueStatusEnum::from($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof IssueStatusEnum) {
            return $value->value;
        }
        throw new \InvalidArgumentException('Invalid value for IssueStatusEnum');
    }

    public function getName(): string
    {
        return 'IssueStatusEnum';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
