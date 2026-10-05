<?php

namespace App\Catalog;

use App\Account\FilmPage;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\Repository\FilmRepository;

/**
 * The films page: the films that can still be booked, selected and sorted by the user.
 */
class FilmCatalog
{
    public function __construct(
        private FilmRepository $filmRepository,
        private SeenFilmService $seenFilmService,
        private UnwantedFilmService $unwantedFilmService,
    ) {
    }

    /**
     * @param int $pageSize one of FilmPage::PAGE_SIZES
     *
     * @return array ['films' => FilmPage (rows of FilmRepository::findShowing, genres decoded, plus 'seen' and 'unwanted'),
     *               'genres' => genres to choose from, 'weeks' => cinema weeks to choose from (their Wednesday, Y-m-d)]
     */
    public function browse(FilmCatalogQuery $query, string $userId, int $pageSize): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $seen = $this->seenFilmService->getSeenFilmSlugs($userId);
        $unwanted = $this->unwantedFilmService->getUnwantedFilmSlugs($userId);
        $excluded = array_merge($query->hideSeen ? $seen : [], $query->hideUnwanted ? $unwanted : []);

        $films = FilmPage::load(
            $this->filmRepository->countShowing($query, $excluded, $now),
            $query->page,
            $pageSize,
            fn (int $offset, int $limit): array => array_map(static fn (array $film): array => [
                'genres' => json_decode((string) $film['genres'], true) ?: [],
            ] + $film + [
                'seen' => in_array($film['slug'], $seen, true),
                'unwanted' => in_array($film['slug'], $unwanted, true),
            ], $this->filmRepository->findShowing($query, $excluded, $now, $offset, $limit)),
        );

        return [
            'films' => $films,
            'genres' => $this->filmRepository->findShowingGenres($now),
            'weeks' => $this->filmRepository->findShowingWeeks($now),
        ];
    }
}
