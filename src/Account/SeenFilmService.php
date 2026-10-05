<?php

namespace App\Account;

use App\Account\Entity\User;
use App\Account\Repository\SeenFilmRepository;
use App\Catalog\Entity\Film;
use App\Catalog\Repository\FilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Films the user says they have already seen (never offered by the planner).
 */
class SeenFilmService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SeenFilmRepository $seenFilmRepository,
        private FilmRepository $filmRepository,
    ) {
    }

    /**
     * @return bool false if the film is unknown
     */
    public function markSeen(string $userId, string $filmSlug): bool
    {
        $film = $this->em->find(Film::class, $filmSlug);
        if (null === $film) {
            return false;
        }

        // INSERT IGNORE: two clicks at the same time must not hit the (user, film) unique key.
        $this->em->getConnection()->executeStatement(
            'INSERT IGNORE INTO seen_film (id, user_id, film_slug, seen_at) VALUES (:id, :user, :film, :seenAt)',
            [
                'id' => Uuid::v7()->toBinary(),
                'user' => Uuid::fromString($userId)->toBinary(),
                'film' => $film->getSlug(),
                'seenAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ],
        );

        return true;
    }

    public function unmarkSeen(string $userId, string $filmSlug): void
    {
        $seenFilm = $this->seenFilmRepository->findOneByUserAndFilm($userId, $filmSlug);
        if (null !== $seenFilm) {
            $this->em->remove($seenFilm);
            $this->em->flush();
        }
    }

    /**
     * @return array slugs of the films already seen
     */
    public function getSeenFilmSlugs(string $userId): array
    {
        return $this->seenFilmRepository->findFilmSlugsByUser($userId);
    }

    /**
     * @param string $order 'asc' or 'desc': sort by title
     *
     * @return array films already seen (same shape as FilmRepository::findBySlugs)
     */
    public function listSeenFilms(string $userId, string $order = 'asc'): array
    {
        return $this->filmRepository->findBySlugs($this->getSeenFilmSlugs($userId), strtoupper($order));
    }
}
