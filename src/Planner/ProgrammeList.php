<?php

namespace App\Planner;

/**
 * The programmes of a plan, best first.
 *
 * @implements \IteratorAggregate<int, Programme>
 */
final readonly class ProgrammeList implements \Countable, \IteratorAggregate
{
    /**
     * @param list<Programme> $programmes
     */
    public function __construct(private array $programmes = [])
    {
    }

    public function isEmpty(): bool
    {
        return [] === $this->programmes;
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->programmes);
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->programmes;
    }

    /**
     * @return list<Programme>
     */
    public function toArray(): array
    {
        return $this->programmes;
    }
}
