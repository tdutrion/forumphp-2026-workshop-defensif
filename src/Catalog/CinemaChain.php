<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * A cinema chain the catalog is fed from. Not to be confused with the planner's ChainBuilder, which chains showtimes.
 */
final readonly class CinemaChain
{
    /**
     * @param string        $id       what the records of the chain store, e.g. "pathe"
     * @param \DateTimeZone $timezone the zone of its cinemas, unless one says otherwise: showtimes are stored in UTC and shown in it
     * @param string        $language ISO 639-1 language of its audience: a film made in it plays in its original version
     */
    public function __construct(
        public string $id,
        public string $name,
        public CountryCode $country,
        public \DateTimeZone $timezone,
        public string $language,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a cinema chain identifier.', $id));
        }
        if (1 !== preg_match('/^[a-z]{2}$/', $language)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an ISO 639-1 language.', $language));
        }
    }
}
