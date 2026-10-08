<?php

namespace App\Catalog;

/**
 * Identifier of a film in the catalog, e.g. 'digger-51293'.
 */
final readonly class FilmSlug implements \Stringable
{
    /** Also the requirement of the routes: a URL that cannot hold a film slug is a 404 before any controller runs. */
    public const PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';
    public const MAX_LENGTH = 150;

    public string $value;

    public function __construct(string $value)
    {
        if (\strlen($value) > self::MAX_LENGTH || 1 !== preg_match('/^'.self::PATTERN.'$/', $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a film slug.', $value));
        }
        $this->value = $value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
