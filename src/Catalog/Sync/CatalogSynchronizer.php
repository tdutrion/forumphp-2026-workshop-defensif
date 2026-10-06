<?php

namespace App\Catalog\Sync;

use App\Catalog\BookingStatus;
use App\Catalog\Entity\Cinema;
use App\Catalog\Entity\City;
use App\Catalog\Entity\Film;
use App\Catalog\Entity\Showtime;
use App\Catalog\Entity\Work;
use App\Catalog\Repository\ShowtimeRepository;
use App\Catalog\Repository\WorkRepository;
use App\Catalog\ShowtimeVersion;
use App\Catalog\WorkLinker;
use App\Sdk\Pathe\CinemaSlug;
use App\Sdk\Pathe\PatheClient;
use App\Sdk\Pathe\PatheMapper;
use App\Sdk\Pathe\ShowSlug;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Copies the cities, cinemas, films and showtimes of the Pathé chain into the catalog.
 */
class CatalogSynchronizer
{
    private const CHAIN = 'pathe';

    public function __construct(
        private PatheClient $client,
        private PatheMapper $mapper,
        private EntityManagerInterface $em,
        private ShowtimeRepository $showtimeRepository,
        private WorkRepository $workRepository,
        private WorkLinker $workLinker,
        private LoggerInterface $logger,
        #[Autowire('%app.chains%')]
        private array $chains,
    ) {
    }

    /**
     * @param array         $citySlugs    cities to synchronize, e.g. ['paris', 'lyon', 'dijon']; empty = every city of the chain
     * @param string|null   $today        first local day synchronized ('Y-m-d'); null = today in the chain's time zone
     * @param int           $days         number of days synchronized
     * @param \Closure|null $stillRunning called before each cinema and each film page, e.g. to keep a lock (a sync of every city takes long)
     *
     * @return array|false ['cities', 'cinemas', 'films', 'showtimes', 'deleted', 'errors', 'linked' (works linked to Wikidata)],
     *                     or false if the Pathé reference data is unreachable
     */
    public function synchronize(array $citySlugs, ?string $today = null, int $days = 7, ?\Closure $stillRunning = null): array|false
    {
        $chain = $this->chains[self::CHAIN];
        $today ??= (new \DateTimeImmutable('now', new \DateTimeZone($chain['timezone'])))->format('Y-m-d');
        $stats = ['cities' => 0, 'cinemas' => 0, 'films' => 0, 'showtimes' => 0, 'deleted' => 0, 'errors' => 0, 'linked' => 0];

        $rawCities = $this->client->getCities();
        $rawCinemas = $this->client->getCinemas();
        $rawShows = $this->client->getShows();
        if (false === $rawCities || false === $rawCinemas || false === $rawShows) {
            return false;
        }

        $cities = [];
        foreach ($rawCities as $raw) {
            if ([] !== $citySlugs && !in_array($raw['slug'], $citySlugs, true)) {
                continue;
            }
            $data = $this->mapper->mapCity($raw);
            $city = $this->em->find(City::class, $data['slug']) ?? (new City())->setSlug($data['slug']);
            $city->setName($data['name'])->setChain(self::CHAIN)->setCountry($chain['country']);
            $this->em->persist($city);
            $cities[$data['slug']] = $city;
            ++$stats['cities'];
        }

        $cinemas = [];
        foreach ($rawCinemas as $raw) {
            $data = $this->mapper->mapCinema($raw);
            if (!isset($cities[$data['citySlug']])) {
                continue;
            }
            $cinema = $this->em->find(Cinema::class, $data['slug']) ?? (new Cinema())->setSlug($data['slug']);
            $cinema
                ->setName($data['name'])
                ->setChain(self::CHAIN)
                ->setCountry($chain['country'])
                ->setTimezone($chain['timezone'])
                ->setLanguage($chain['language'])
                ->setCity($cities[$data['citySlug']])
                ->setAddress($data['address'])
                ->setPostalCode($data['postalCode'])
                ->setTown($data['town'])
                ->setLatitude($data['position']?->latitude)
                ->setLongitude($data['position']?->longitude)
                ->setHallCount($data['hallCount'])
                ->setOpen($data['open']);
            $this->em->persist($cinema);
            $cinemas[$data['slug']] = $cinema;
            ++$stats['cinemas'];
        }

        // A cinema of a synchronized city that Pathé no longer lists has closed: its showtimes are deleted below.
        if ([] !== $cities) {
            foreach ($this->em->getRepository(Cinema::class)->findBy(['city' => array_keys($cities), 'open' => true]) as $cinema) {
                if (!isset($cinemas[$cinema->getSlug()])) {
                    $cinema->setOpen(false);
                    $cinemas[$cinema->getSlug()] = $cinema;
                }
            }
        }

        $films = [];
        foreach ($rawShows as $raw) {
            $data = $this->mapper->mapFilm($raw);
            if (false === $data) {
                continue;
            }
            $film = $this->em->find(Film::class, $data['slug']);
            if (null === $film) {
                // A new film is a new work until its film page tells more (see WorkLinker).
                $year = null !== $data['releaseDate'] ? (int) substr($data['releaseDate'], 0, 4) : null;
                $film = (new Film())->setSlug($data['slug'])->setWork((new Work())->describe($data['title'], $year, null));
            }
            $film
                ->setTitle($data['title'])
                ->setChain(self::CHAIN)
                ->setDuration($data['duration'])
                ->setReleaseDate(null !== $data['releaseDate'] ? new \DateTimeImmutable($data['releaseDate']) : null)
                ->setGenres($data['genres'])
                ->setPosterUrl($data['posterUrl'])
                ->setContentRating($data['contentRating']);
            $this->em->persist($film);
            $films[$data['slug']] = $film;
            ++$stats['films'];
        }
        $this->em->flush();

        $lastDay = (new \DateTimeImmutable($today))->modify('+'.($days - 1).' days')->format('Y-m-d');
        $timezone = new \DateTimeZone($chain['timezone']);

        $playing = [];
        foreach ($cinemas as $cinemaSlug => $cinema) {
            if (null !== $stillRunning) {
                $stillRunning();
            }
            if (!$cinema->isOpen()) {
                $stats['deleted'] += $this->showtimeRepository->deleteForCinemasBetween([$cinemaSlug], $today, $lastDay, []);
                continue;
            }

            $patheCinema = new CinemaSlug($cinemaSlug);
            $programme = $this->client->getCinemaProgramme($patheCinema);
            if (false === $programme) {
                ++$stats['errors'];
                continue;
            }

            $keptIds = [];
            $complete = true;
            // Kept only to detach them once flushed below: a sync of every cinema must not keep every
            // showtime of every day in the identity map, or it runs out of memory before the end.
            $cinemaShowtimes = [];
            foreach ($this->mapper->showSlugsPlayingBetween($programme, $today, $lastDay) as $showSlug) {
                if (!isset($films[$showSlug])) {
                    continue;
                }

                $playing[$showSlug] = $films[$showSlug];
                $showtimes = $this->client->getShowtimes(showSlug: new ShowSlug($showSlug), cinemaSlug: $patheCinema);
                if (false === $showtimes) {
                    ++$stats['errors'];
                    $complete = false;
                    continue;
                }

                foreach ($this->mapper->mapShowtimes(showtimes: $showtimes, timezone: $timezone) as $data) {
                    if ($data['localDate'] < $today || $data['localDate'] > $lastDay) {
                        continue;
                    }
                    // Pathé's strings become enums here: an unknown version or status is not guessed.
                    $version = ShowtimeVersion::tryFrom($data['version']);
                    $status = BookingStatus::tryFrom($data['status']);
                    if (null === $version || null === $status) {
                        $this->logger->warning('Unknown version or status, showtime skipped', ['showtime' => $data['id'], 'version' => $data['version'], 'status' => $data['status']]);
                        continue;
                    }
                    $showtime = $this->em->find(Showtime::class, $data['id']) ?? (new Showtime())->setId($data['id']);
                    $showtime
                        ->setFilm($films[$showSlug])
                        ->setCinema($cinema)
                        ->setStartsAt(new \DateTimeImmutable($data['startsAt']))
                        ->setEndsAt(new \DateTimeImmutable($data['endsAt']))
                        ->setLocalDate(new \DateTimeImmutable($data['localDate']))
                        ->setVersion($version)
                        ->setStatus($status)
                        ->setBookingUrl($data['bookingUrl'])
                        ->setReservableUntil(null !== $data['reservableUntil'] ? new \DateTimeImmutable($data['reservableUntil']) : null)
                        ->setAuditorium($data['auditorium'])
                        ->setCapacity($data['capacity']);
                    $this->em->persist($showtime);
                    $cinemaShowtimes[] = $showtime;
                    $keptIds[] = $data['id'];
                    ++$stats['showtimes'];
                }
            }
            $this->em->flush();
            foreach ($cinemaShowtimes as $showtime) {
                $this->em->detach($showtime);
            }

            // Vanished showtimes are deleted only when the whole schedule of the cinema could be read.
            if ($complete) {
                $stats['deleted'] += $this->showtimeRepository->deleteForCinemasBetween([$cinemaSlug], $today, $lastDay, $keptIds);
            } else {
                $this->logger->warning('Incomplete schedule, no showtime deleted', ['cinema' => $cinemaSlug]);
            }
        }

        // Original language (VOST/VO filter), synopsis and work of the films that play, read once from their film page.
        foreach ($playing as $showSlug => $film) {
            if (null !== $stillRunning) {
                $stillRunning();
            }
            if (null !== $film->getOriginalLanguage() && null !== $film->getSynopsis() && null !== $film->getWork()->getDirectors()) {
                continue;
            }
            $rawShow = $this->client->getShow(new ShowSlug($showSlug));
            if (false === $rawShow) {
                $this->logger->warning('Film page unreadable, original language, synopsis and work unknown', ['film' => $showSlug]);
                continue;
            }
            $film->setOriginalLanguage($this->mapper->mapOriginalLanguage($rawShow));
            $film->setSynopsis($this->mapper->mapSynopsis($rawShow));
            $details = $this->mapper->mapFilmDetails($rawShow);
            $this->workLinker->describe($film, $details['originalTitle'] ?? (string) $film->getTitle(), $details['year'], $details['directors']);
        }
        $this->em->flush();

        // Works not linked yet are looked up in Wikidata, at most once a day each; Wikidata failing never stops the sync.
        $stats['linked'] = $this->workLinker->linkDue(
            $this->workRepository->findWorksOfFilms(array_keys($playing)),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        // Showtimes of past days are of no use to anyone: the catalog must not grow forever.
        $stats['deleted'] += $this->showtimeRepository->deleteBefore($today);

        return $stats;
    }
}
