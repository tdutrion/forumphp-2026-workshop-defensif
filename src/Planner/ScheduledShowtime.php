<?php

namespace App\Planner;

use App\Catalog\Coordinates;
use App\Catalog\ShowtimeVersion;

/**
 * A showtime the planner may chain: what the search needs to know about it, and, once it sits in a
 * programme, how it follows the previous one.
 */
final readonly class ScheduledShowtime
{
    /**
     * @param string $workId        the work of the film (common to every chain), hexadecimal
     * @param int    $lateMinutes   how late one arrives after the start (the ads), 0 in the first showtime
     * @param int    $breakMinutes  from the end of the previous film to this start
     * @param int    $travelMinutes the part of the break spent changing cinema
     */
    public function __construct(
        public string $id,
        public string $filmSlug,
        public string $filmTitle,
        public string $workId,
        public string $cinemaSlug,
        public string $cinemaName,
        public Coordinates $position,
        public ScreeningTime $start,
        public ScreeningTime $end,
        public \DateTimeZone $timezone,
        public ShowtimeVersion $version,
        public string $bookingUrl,
        public int $lateMinutes = 0,
        public int $breakMinutes = 0,
        public int $travelMinutes = 0,
    ) {
    }

    /**
     * @param array $row a row of ShowtimeRepository::findCandidates(): the instants are in UTC
     *
     * @throws \DateMalformedStringException
     * @throws \DateInvalidTimeZoneException
     * @throws \InvalidArgumentException     on a position that does not exist
     * @throws \ValueError                   on an unknown version
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: $row['id'],
            filmSlug: $row['filmSlug'],
            filmTitle: $row['filmTitle'],
            workId: $row['workId'],
            cinemaSlug: $row['cinemaSlug'],
            cinemaName: $row['cinemaName'],
            position: new Coordinates((float) $row['latitude'], (float) $row['longitude']),
            start: ScreeningTime::fromUtc($row['startsAt']),
            end: ScreeningTime::fromUtc($row['endsAt']),
            timezone: new \DateTimeZone($row['timezone']),
            version: ShowtimeVersion::from($row['version']),
            bookingUrl: $row['bookingUrl'],
        );
    }

    /**
     * The same showtime, as it follows the previous one in a programme.
     */
    #[\NoDiscard('ScheduledShowtime is immutable: withTransition() returns the showtime that follows another.')]
    public function withTransition(int $lateMinutes, int $breakMinutes, int $travelMinutes): self
    {
        return clone ($this, [
            'lateMinutes' => $lateMinutes,
            'breakMinutes' => $breakMinutes,
            'travelMinutes' => $travelMinutes,
        ]);
    }

    /**
     * Start, in the local time of the cinema.
     */
    public function startTime(): string
    {
        return $this->start->localTime($this->timezone)->format('H:i');
    }

    /**
     * End of the film, in the local time of the cinema.
     */
    public function endTime(): string
    {
        return $this->end->localTime($this->timezone)->format('H:i');
    }
}
