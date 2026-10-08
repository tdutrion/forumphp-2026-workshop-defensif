<?php

namespace App\Catalog;

/**
 * A film that the catalog was asked for does not exist.
 */
final class FilmNotFound extends \RuntimeException
{
    public function __construct(public readonly FilmSlug $slug, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('No film "%s" in the catalog.', $slug), 0, $previous);
    }
}
