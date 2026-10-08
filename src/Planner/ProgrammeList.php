<?php

namespace App\Planner;

/**
 * The programmes of a plan, best first.
 *
 * @implements \IteratorAggregate<int, array>
 */
final readonly class ProgrammeList implements \Countable, \IteratorAggregate
{
    /**
     * @param list<array> $programmes programmes as produced by ChainBuilder::build() and formatted by PlannerService
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
     * @return list<array>
     */
    public function toArray(): array
    {
        return $this->programmes;
    }
}
