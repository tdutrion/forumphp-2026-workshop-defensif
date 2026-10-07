<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Catalog\CountryCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * A CountryCode in a CHAR(2)-sized string column.
 */
final class CountryCodeType extends Type
{
    public const string NAME = 'country_code';

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
        if (!$value instanceof CountryCode) {
            throw InvalidType::new($value, self::NAME, [CountryCode::class, 'null']);
        }

        return $value->value;
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CountryCode
    {
        if (null === $value) {
            return null;
        }

        try {
            return new CountryCode((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ValueNotConvertible::new((string) $value, self::NAME, $e->getMessage(), $e);
        }
    }
}
