<?php

declare(strict_types=1);

namespace App\Sdk\Pathe;

/**
 * Identifier of a cinema at Pathé, e.g. 'cinema-pathe-dijon'.
 */
final readonly class CinemaSlug
{
    public string $value;

    public function __construct(string $value)
    {
        if (1 !== preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a Pathé cinema slug.', $value));
        }
        $this->value = $value;
    }
}
