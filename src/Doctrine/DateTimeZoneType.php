<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * A \DateTimeZone stored by its IANA name, e.g. "Europe/Paris".
 */
final class DateTimeZoneType extends Type
{
    public const string NAME = 'date_time_zone';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof \DateTimeZone) {
            throw InvalidType::new($value, self::NAME, [\DateTimeZone::class, 'null']);
        }

        return $value->getName();
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeZone
    {
        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeZone((string) $value);
        } catch (\DateInvalidTimeZoneException $e) {
            throw ValueNotConvertible::new((string) $value, self::NAME, $e->getMessage(), $e);
        }
    }
}
