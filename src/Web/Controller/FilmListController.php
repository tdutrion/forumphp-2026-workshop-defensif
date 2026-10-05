<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The films of the user, page by page: their history (films already seen) and the films not for them.
 * The page is in the URL, and the buttons of a row come back to it (Post/Redirect/Get).
 */
class FilmListController extends AbstractController
{
    public function __construct(
        private SeenFilmService $seenFilmService,
        private UnwantedFilmService $unwantedFilmService,
    ) {
    }

    #[Route('/history', name: 'app_history', methods: ['GET'])]
    public function history(#[CurrentUser] User $user, #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1): Response
    {
        return $this->render('film_list/history.html.twig', [
            'films' => $this->seenFilmService->pageOfSeenFilms($user->getUserIdentifier(), $page),
        ]);
    }

    #[Route('/not-for-me', name: 'app_unwanted_films', methods: ['GET'])]
    public function unwanted(#[CurrentUser] User $user, #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1): Response
    {
        return $this->render('film_list/unwanted.html.twig', [
            'films' => $this->unwantedFilmService->pageOfUnwantedFilms($user->getUserIdentifier(), $page),
        ]);
    }
}
