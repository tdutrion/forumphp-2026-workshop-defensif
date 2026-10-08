<?php

namespace App\Planner;

/**
 * Showtimes that a search considers, or that a programme chains: a list, in a given order, of showtimes only.
 *
 * @implements \IteratorAggregate<int, ScheduledShowtime>
 */
final readonly class ScheduledShowtimeList implements \Countable, \IteratorAggregate
{
    /** @var list<ScheduledShowtime> */
    private array $showtimes;

    public function __construct(ScheduledShowtime ...$showtimes)
    {
        $this->showtimes = $showtimes;
    }

    public function isEmpty(): bool
    {
        return [] === $this->showtimes;
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->showtimes);
    }

    /**
     * @return \Traversable<int, ScheduledShowtime>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->showtimes;
    }

    /**
     * @return list<ScheduledShowtime>
     */
    public function toArray(): array
    {
        return $this->showtimes;
    }

    public function first(): ?ScheduledShowtime
    {
        return array_first($this->showtimes);
    }

    public function last(): ?ScheduledShowtime
    {
        return array_last($this->showtimes);
    }

    /**
     * The same list, one showtime further.
     */
    #[\NoDiscard('ScheduledShowtimeList is immutable: with() returns the longer list.')]
    public function with(ScheduledShowtime $showtime): self
    {
        return new self(...$this->showtimes, ...[$showtime]);
    }

    /**
     * Whether one of the showtimes is of that work (common to every chain).
     */
    public function hasWork(string $workId): bool
    {
        return array_any($this->showtimes, static fn (ScheduledShowtime $showtime): bool => $showtime->workId === $workId);
    }

    #[\NoDiscard]
    public function sortedByStart(): self
    {
        $sorted = $this->showtimes;
        usort($sorted, static fn (ScheduledShowtime $a, ScheduledShowtime $b): int => ScreeningTime::compare($a->start, $b->start));

        return new self(...$sorted);
    }

    /**
     * @return list<string>
     */
    public function filmSlugs(): array
    {
        return array_map(static fn (ScheduledShowtime $showtime): string => $showtime->filmSlug, $this->showtimes);
    }

    /**
     * @return list<string>
     */
    public function workIds(): array
    {
        return array_map(static fn (ScheduledShowtime $showtime): string => $showtime->workId, $this->showtimes);
    }

    /**
     * @param \Closure(ScheduledShowtime): int $minutes
     */
    public function sum(\Closure $minutes): int
    {
        return array_sum(array_map($minutes, $this->showtimes));
    }
}
