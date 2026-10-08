<?php

namespace App\Planner;

use Time\Duration;

/**
 * An instant of a showtime, always held in UTC: the local time of a cinema is only computed to be shown.
 */
final readonly class ScreeningTime
{
    private \DateTimeImmutable $instant;

    public function __construct(\DateTimeImmutable $instant)
    {
        $this->instant = $instant->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * @param string $utc an instant as the database stores it, e.g. '2026-10-08 14:00:00'
     *
     * @throws \DateMalformedStringException
     */
    public static function fromUtc(string $utc): self
    {
        return new self(new \DateTimeImmutable($utc, new \DateTimeZone('UTC')));
    }

    /**
     * Returns -1, 0, 1 if $a is before, at the same instant as, or after $b, like Duration::compare().
     */
    public static function compare(self $a, self $b): int
    {
        return $a->instant <=> $b->instant;
    }

    public function isAfter(self $other): bool
    {
        return self::compare($this, $other) > 0;
    }

    public function localTime(\DateTimeZone $timezone): \DateTimeImmutable
    {
        return $this->instant->setTimezone($timezone);
    }

    #[\NoDiscard('ScreeningTime is immutable: plus() returns the later time.')]
    public function plus(Duration $duration): self
    {
        $microseconds = self::microseconds($duration);

        return new self($this->instant->modify(sprintf('%+d microseconds', $duration->negative ? -$microseconds : $microseconds)));
    }

    /**
     * Time from this instant to $other: negative when $other comes first.
     */
    #[\NoDiscard]
    public function until(self $other): Duration
    {
        $microseconds = self::epochMicroseconds($other->instant) - self::epochMicroseconds($this->instant);
        $duration = Duration::fromMicroseconds(abs($microseconds));

        return $microseconds < 0 ? $duration->negate() : $duration;
    }

    private static function microseconds(Duration $duration): int
    {
        return $duration->seconds * 1_000_000 + intdiv($duration->nanoseconds, 1_000);
    }

    private static function epochMicroseconds(\DateTimeImmutable $instant): int
    {
        return $instant->getTimestamp() * 1_000_000 + (int) $instant->format('u');
    }
}
