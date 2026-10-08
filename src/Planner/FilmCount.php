<?php

namespace App\Planner;

/**
 * Number of films of a marathon, from 1 to 8.
 */
final readonly class FilmCount
{
    public const MIN = 1;
    public const MAX = 8;
    public const DEFAULT = 2;

    public function __construct(public int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new \InvalidArgumentException(\sprintf('A marathon has %d to %d films, not %d.', self::MIN, self::MAX, $value));
        }
    }

    /**
     * One film fewer, to fall back on when no marathon is possible: never fewer than a single film.
     */
    public function fewer(): self
    {
        return new self(clamp($this->value - 1, self::MIN, self::MAX));
    }

    public function isSingle(): bool
    {
        return self::MIN === $this->value;
    }

    public function isFewerThan(self $other): bool
    {
        return $this->value < $other->value;
    }
}
