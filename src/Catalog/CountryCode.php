<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Component\Intl\Countries;

/**
 * An ISO 3166-1 alpha-2 country code that exists, e.g. "FR".
 */
final readonly class CountryCode implements \Stringable
{
    public string $value;

    /**
     * @throws \InvalidArgumentException if no country has this code
     */
    public function __construct(string $value)
    {
        $code = strtoupper($value);
        if (!Countries::exists($code)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an ISO 3166-1 alpha-2 country code.', $value));
        }
        $this->value = $code;
    }

    /**
     * The name of the country in the language of the reader.
     */
    public function name(string $locale): string
    {
        return Countries::getName($this->value, $locale);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
