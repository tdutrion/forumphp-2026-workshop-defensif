<?php

namespace App\Sdk\Pathe;

/**
 * Showtimes of a film in a cinema, as one list whatever the shape of Pathé's response.
 */
final readonly class PatheShowtimes
{
    /**
     * @param list<array> $items raw showtimes ('time', 'endTime', 'refCmd'...), all days together
     */
    private function __construct(
        public array $items,
    ) {
    }

    /**
     * @param array $raw response from /show/{slug}/showtimes/{cinema}: a JSON list [] when there is
     *                   no showtime, otherwise an object of lists of showtimes indexed by date
     *
     * @throws \InvalidArgumentException if the response has neither shape
     */
    public static function fromApiResponse(array $raw): self
    {
        if (array_is_list($raw)) {
            if ([] !== $raw) {
                throw new \InvalidArgumentException('Pathé showtimes: a list is only expected when empty.');
            }

            return new self([]);
        }

        $items = [];
        foreach ($raw as $date => $showtimes) {
            if (!is_array($showtimes) || !array_is_list($showtimes)) {
                throw new \InvalidArgumentException(sprintf('Pathé showtimes of %s: a list was expected.', $date));
            }
            foreach ($showtimes as $showtime) {
                if (!is_array($showtime)) {
                    throw new \InvalidArgumentException(sprintf('Pathé showtimes of %s: each showtime should be an object.', $date));
                }
                $items[] = $showtime;
            }
        }

        return new self($items);
    }
}
