<?php

namespace App\Account;

use App\Account\Entity\SeenFilm;
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

        if (null === $this->seenFilmRepository->findOneByUserAndFilm($userId, $filmSlug)) {
            $seenFilm = (new SeenFilm())
                ->setUser($this->em->getReference(User::class, Uuid::fromString($userId)))
                ->setFilm($film);
            $this->em->persist($seenFilm);
            $this->em->flush();
        }

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
     * @return int number of films added to the list
     */
    public function markAllSeen(string $userId, array $filmSlugs): int
    {
        $added = 0;
        $alreadySeen = $this->getSeenFilmSlugs($userId);
        foreach (array_unique($filmSlugs) as $slug) {
            if (!in_array($slug, $alreadySeen, true) && $this->markSeen($userId, $slug)) {
                ++$added;
            }
        }

        return $added;
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
