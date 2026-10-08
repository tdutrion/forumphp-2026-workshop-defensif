<?php

declare(strict_types=1);

namespace App\Planner;

/**
 * The programmes of a plan, best first.
 *
 * @implements \IteratorAggregate<int, Programme>
 */
final readonly class ProgrammeList implements \Countable, \IteratorAggregate
{
    /** @var list<Programme> */
    private array $programmes;

    public function __construct(Programme ...$programmes)
    {
        $this->programmes = $programmes;
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

    /**
     * @return \Traversable<int, Programme>
     */
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

    public function first(): ?Programme
    {
        return array_first($this->programmes);
    }

    #[\NoDiscard('ProgrammeList is immutable: with() returns the longer list.')]
    public function with(Programme $programme): self
    {
        return new self(...$this->programmes, ...[$programme]);
    }

    /**
     * The $max first programmes.
     */
    #[\NoDiscard]
    public function take(int $max): self
    {
        return new self(...\array_slice($this->programmes, 0, $max));
    }

    /**
     * @param \Closure(Programme, Programme): int $compare
     */
    #[\NoDiscard]
    public function sortedBy(\Closure $compare): self
    {
        $sorted = $this->programmes;
        usort($sorted, $compare);

        return new self(...$sorted);
    }

    /**
     * @param \Closure(Programme): bool $keep
     */
    #[\NoDiscard]
    public function filter(\Closure $keep): self
    {
        return new self(...array_values(array_filter($this->programmes, $keep)));
    }

    /**
     * @return list<mixed>
     */
    public function map(\Closure $transform): array
    {
        return array_map($transform, $this->programmes);
    }
}
