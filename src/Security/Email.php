<?php

declare(strict_types=1);

namespace App\Security;

/**
 * An email address as the application compares it: in lower case ("Ada@Example.org" is "ada@example.org",
 * but "josé@" is never "jose@": the column is case- and accent-sensitive).
 */
abstract readonly class Email
{
    public string $value;

    /**
     * @throws \InvalidArgumentException if it is not an address
     */
    public function __construct(string $value)
    {
        if (1 !== preg_match('/^[^@\s]+@[^@\s]+$/u', $value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an email address.', $value));
        }
        $this->value = mb_strtolower($value);
    }
}
