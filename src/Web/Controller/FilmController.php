<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\FilmNotFound;
use App\Catalog\FilmSchedule;
use App\Catalog\FilmSlug;
use App\Catalog\Repository\FilmRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class FilmController extends AbstractController
{
    #[Route('/films/{slug}', name: 'app_film_show', requirements: ['slug' => FilmSlug::PATTERN], methods: ['GET'])]
    public function show(FilmSlug $slug, FilmRepository $filmRepository, FilmSchedule $filmSchedule, SeenFilmService $seenFilmService, UnwantedFilmService $unwantedFilmService, #[CurrentUser] User $user): Response
    {
        try {
            $film = $filmRepository->getFilm($slug);
        } catch (FilmNotFound $e) {
            throw $this->createNotFoundException('error.film_not_found', $e);
        }

        return $this->render('film/show.html.twig', [
            'film' => $film,
            'seen' => in_array($slug->value, $seenFilmService->getSeenFilmSlugs($user->getUserIdentifier()), true),
            'unwanted' => in_array($slug->value, $unwantedFilmService->getUnwantedFilmSlugs($user->getUserIdentifier()), true),
            'cinemas' => $filmSchedule->forFilm($slug->value),
        ]);
    }
}
