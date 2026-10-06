<?php

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
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
    #[Route('/api/films/{slug}', name: 'api_film', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Film details, with seen = already seen by the user, and its work (common to every chain) with its Wikidata, IMDb and TMDB ids')]
    #[OA\Response(response: 404, description: 'Unknown film')]
    public function show(string $slug, FilmRepository $filmRepository, SeenFilmService $seenFilmService, UnwantedFilmService $unwantedFilmService, #[CurrentUser] User $user): JsonResponse
    {
        $film = $filmRepository->findBySlug($slug);
        if (null === $film) {
            throw new NotFoundHttpException('error.film_not_found');
        }

        return new JsonResponse([
            'slug' => $film['slug'],
            'title' => $film['title'],
            'duration' => $film['duration'],
            'releaseDate' => $film['releaseDate']?->format('Y-m-d'),
            'genres' => $film['genres'],
            'posterUrl' => $film['posterUrl'],
            'contentRating' => $film['contentRating'],
            'seen' => in_array($slug, $seenFilmService->getSeenFilmSlugs($user->getUserIdentifier()), true),
            'unwanted' => in_array($slug, $unwantedFilmService->getUnwantedFilmSlugs($user->getUserIdentifier()), true),
            // The work, common to every chain, and its open data ids (null when unknown).
            'work' => ['id' => $film['workId'], 'wikidataId' => $film['wikidataId'], 'imdbId' => $film['imdbId'], 'tmdbId' => $film['tmdbId']],
        ]);
    }
}
