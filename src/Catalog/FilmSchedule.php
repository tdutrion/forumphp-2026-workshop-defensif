<?php

namespace App\Catalog;

use App\Catalog\Repository\ShowtimeRepository;

/**
 * The known showtimes of a film, as shown on its page: by cinema, then by local day.
 */
class FilmSchedule
{
    public function __construct(private ShowtimeRepository $showtimeRepository)
    {
    }

    /**
     * @return array list of cinemas ['slug', 'name', 'city', 'count' (showtimes),
     *               'days' => list of ['date' (local 'Y-m-d'), 'showtimes' => list of ['time' (local 'H:i'), 'version', 'bookingUrl']]],
     *               by city then name; [] when no showtime can be booked any more
     */
    public function forFilm(string $filmSlug): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cinemas = [];
        foreach ($this->showtimeRepository->findBookableForFilm($filmSlug, $now->format('Y-m-d H:i:s')) as $row) {
            $cinema = $cinemas[$row['cinemaSlug']] ??= ['slug' => $row['cinemaSlug'], 'name' => $row['cinemaName'], 'city' => $row['cityName'], 'count' => 0, 'days' => []];
            // The database stores UTC instants: the time is shown in the time zone of the cinema.
            $start = (new \DateTimeImmutable($row['startsAt'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($row['timezone']));
            $cinema['days'][$row['localDate']]['date'] = $row['localDate'];
            $cinema['days'][$row['localDate']]['showtimes'][] = ['time' => $start->format('H:i'), 'version' => $row['version'], 'bookingUrl' => $row['bookingUrl']];
            ++$cinema['count'];
            $cinemas[$row['cinemaSlug']] = $cinema;
        }

        return array_values(array_map(static function (array $cinema): array {
            $cinema['days'] = array_values($cinema['days']);

            return $cinema;
        }, $cinemas));
    }
}
