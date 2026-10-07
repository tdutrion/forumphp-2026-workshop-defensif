<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\FilmNotFound;
use App\Catalog\FilmSlug;
use App\Catalog\Repository\FilmRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Catalog')]
class FilmController extends AbstractController
{
    #[Route('/api/films/{slug}', name: 'api_film', requirements: ['slug' => FilmSlug::PATTERN], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Film details, with seen = already seen by the user, and its work (common to every chain) with its Wikidata, IMDb and TMDB ids')]
    #[OA\Response(response: 404, description: 'Unknown film')]
    public function show(FilmSlug $slug, FilmRepository $filmRepository, SeenFilmService $seenFilmService, UnwantedFilmService $unwantedFilmService, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $film = $filmRepository->getFilm($slug);
        } catch (FilmNotFound $e) {
            throw new NotFoundHttpException('error.film_not_found', $e);
        }
        $work = $film->getWork();

        return new JsonResponse([
            'slug' => $film->getSlug(),
            'title' => $film->getTitle(),
            'duration' => $film->getDuration(),
            'releaseDate' => $film->getReleaseDate()?->format('Y-m-d'),
            'genres' => $film->getGenres(),
            'posterUrl' => $film->getPosterUrl(),
            'contentRating' => $film->getContentRating(),
            'seen' => in_array($slug->value, $seenFilmService->getSeenFilmSlugs($user->getUserIdentifier()), true),
            'unwanted' => in_array($slug->value, $unwantedFilmService->getUnwantedFilmSlugs($user->getUserIdentifier()), true),
            // The work, common to every chain, and its open data ids (null when unknown).
            'work' => ['id' => $work->getId()->toRfc4122(), 'wikidataId' => $work->getWikidataId(), 'imdbId' => $work->getImdbId(), 'tmdbId' => $work->getTmdbId()],
        ]);
    }
}
