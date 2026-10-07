<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Planner\ScheduledShowtime;

final readonly class ShowtimeResource
{
    public function __construct(
        public string $id,
        public FilmSummaryResource $film,
        public CinemaResource $cinema,
        /** ISO 8601 in the cinema's time zone: the offset lets a mobile app convert it. */
        public string $startsAt,
        public string $endsAt,
        public string $version,
        public int $lateMinutes,
        /** Minutes from the end of the previous film to this showtime, and the travel among them. */
        public int $breakMinutes,
        public int $travelMinutes,
        public string $bookingUrl,
    ) {
    }

    public static function fromShowtime(ScheduledShowtime $showtime): self
    {
        return new self(
            $showtime->id,
            new FilmSummaryResource($showtime->filmSlug, $showtime->filmTitle),
            new CinemaResource($showtime->cinemaSlug, $showtime->cinemaName),
            $showtime->start->localTime($showtime->timezone)->format(\DATE_ATOM),
            $showtime->end->localTime($showtime->timezone)->format(\DATE_ATOM),
            $showtime->version->value,
            $showtime->lateMinutes,
            $showtime->breakMinutes,
            $showtime->travelMinutes,
            $showtime->bookingUrl,
        );
    }
}
