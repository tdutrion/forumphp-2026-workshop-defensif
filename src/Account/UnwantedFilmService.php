<?php

namespace App\Account;

use App\Account\Repository\UnwantedFilmRepository;
use App\Catalog\Entity\Film;
use App\Catalog\Repository\FilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Films the user does not want to see (never offered by the planner, like the films already seen).
 */
class UnwantedFilmService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UnwantedFilmRepository $unwantedFilmRepository,
        private FilmRepository $filmRepository,
    ) {
    }

    /**
     * @return bool false if the film is unknown
     */
    public function markUnwanted(string $userId, string $filmSlug): bool
    {
        $film = $this->em->find(Film::class, $filmSlug);
        if (null === $film) {
            return false;
        }

        // INSERT IGNORE: two clicks at the same time must not hit the (user, film) unique key.
        $this->em->getConnection()->executeStatement(
            'INSERT IGNORE INTO unwanted_film (id, user_id, film_slug, created_at) VALUES (:id, :user, :film, :createdAt)',
            [
                'id' => Uuid::v7()->toBinary(),
                'user' => Uuid::fromString($userId)->toBinary(),
                'film' => $film->getSlug(),
                'createdAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ],
        );

        return true;
    }

    public function unmarkUnwanted(string $userId, string $filmSlug): void
    {
        $unwantedFilm = $this->unwantedFilmRepository->findOneByUserAndFilm($userId, $filmSlug);
        if (null !== $unwantedFilm) {
            $this->em->remove($unwantedFilm);
            $this->em->flush();
        }
    }

    /**
     * @return array slugs of the films the user does not want to see
     */
    public function getUnwantedFilmSlugs(string $userId): array
    {
        return $this->unwantedFilmRepository->findFilmSlugsByUser($userId);
    }

    /**
     * @return array films in the FilmRepository::findBySlugs() format, sorted by title
     */
    public function listUnwantedFilms(string $userId): array
    {
        return $this->filmRepository->findBySlugs($this->getUnwantedFilmSlugs($userId));
    }

    /**
     * @param int $page from 1; a page after the last one gives the last one
     */
    public function pageOfUnwantedFilms(string $userId, int $page): FilmPage
    {
        return FilmPage::load(
            $this->unwantedFilmRepository->countByUser($userId),
            $page,
            fn (int $offset, int $limit): array => $this->unwantedFilmRepository->findPageByUser($userId, $offset, $limit),
        );
    }
}
