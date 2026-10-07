<?php

declare(strict_types=1);

namespace App\Account;

use App\Account\Entity\User;
use App\Account\Repository\SeenFilmRepository;
use App\Catalog\Entity\Film;
use App\Catalog\Repository\FilmRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
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
        private ClockInterface $clock,
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

        // INSERT IGNORE: two clicks at the same time must not hit the (user, work) unique key.
        $this->em->getConnection()->executeStatement(
            'INSERT IGNORE INTO seen_film (id, user_id, work_id, seen_at) VALUES (:id, :user, :work, :seenAt)',
            [
                'id' => Uuid::v7()->toBinary(),
                'user' => Uuid::fromString($userId)->toBinary(),
                'work' => $film->getWork()->getId()->toBinary(),
                'seenAt' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );

        return true;
    }

    public function unmarkSeen(string $userId, string $filmSlug): void
    {
        $film = $this->em->find(Film::class, $filmSlug);
        $seenFilm = null === $film ? null : $this->seenFilmRepository->findOneByUserAndWork($userId, $film->getWork());
        if (null !== $seenFilm) {
            $this->em->remove($seenFilm);
            $this->em->flush();
        }
    }

    /**
     * @return list<string> slugs of the films already seen
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

    /**
     * @param int $page     from 1; a page after the last one gives the last one
     * @param int $pageSize one of FilmPage::PAGE_SIZES
     */
    public function pageOfSeenFilms(string $userId, int $page, int $pageSize): FilmPage
    {
        return FilmPage::load(
            $this->seenFilmRepository->countByUser($userId),
            $page,
            $pageSize,
            fn (int $offset, int $limit): array => $this->seenFilmRepository->findPageByUser($userId, $offset, $limit),
        );
    }
}
