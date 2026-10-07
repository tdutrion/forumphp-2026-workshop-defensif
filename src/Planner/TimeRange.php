<?php

declare(strict_types=1);

namespace App\Planner;

/**
 * Time range of a search, in the local time of each cinema: a showtime starts at or after $from and
 * ends at or before $until, on the same day. Each limit is optional; the range never ends after midnight.
 */
final readonly class TimeRange
{
    private ?\DateTimeImmutable $start;
    private ?\DateTimeImmutable $end;

    /**
     * @param ?string $from  earliest start, local 'H:i' (null: no limit)
     * @param ?string $until latest end, local 'H:i' after $from (null: no limit)
     */
    public function __construct(public ?string $from = null, public ?string $until = null)
    {
        $this->start = self::timeOfDay($from);
        $this->end = self::timeOfDay($until);
        if (null !== $this->start && null !== $this->end && $this->end <= $this->start) {
            throw new \InvalidArgumentException(\sprintf('The time range ends at %s, before it starts at %s.', $until, $from));
        }
    }

    /**
     * Whether a showtime of the local day $date (Y-m-d) of a cinema fits in the range.
     */
    public function contains(ScreeningTime $start, ScreeningTime $end, string $date, \DateTimeZone $timezone): bool
    {
        // Local instants of the limits: the day of a clock change has 23 or 25 hours.
        if (null !== $this->from && new ScreeningTime(new \DateTimeImmutable($date.' '.$this->from, $timezone))->isAfter($start)) {
            return false;
        }

        return null === $this->until || !$end->isAfter(new ScreeningTime(new \DateTimeImmutable($date.' '.$this->until, $timezone)));
    }

    /**
     * Compared as times of day, not as text.
     */
    private static function timeOfDay(?string $time): ?\DateTimeImmutable
    {
        if (null === $time) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!H:i', $time, new \DateTimeZone('UTC'));
        // "25:99" overflows into another valid time: only a time that reads back the same is one.
        if (false === $parsed || $parsed->format('H:i') !== $time) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a time of day (H:i).', $time));
        }

        return $parsed;
    }
}
