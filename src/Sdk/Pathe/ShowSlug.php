<?php

namespace App\Sdk\Pathe;

/**
 * Identifier of a film (or an event) at Pathé, e.g. 'digger-51293'.
 */
final readonly class ShowSlug
{
    public string $value;

    public function __construct(string $value)
    {
        if (1 !== preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a Pathé show slug.', $value));
        }
        $this->value = $value;
    }
}
