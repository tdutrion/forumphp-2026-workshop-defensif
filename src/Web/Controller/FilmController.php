<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\Repository\FilmRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class FilmController extends AbstractController
{
    #[Route('/films/{slug}', name: 'app_film_show', methods: ['GET'])]
    public function show(string $slug, FilmRepository $filmRepository, SeenFilmService $seenFilmService, UnwantedFilmService $unwantedFilmService, #[CurrentUser] User $user): Response
    {
        $film = $filmRepository->findBySlug($slug);
        if (null === $film) {
            throw $this->createNotFoundException('error.film_not_found');
        }

        return $this->render('film/show.html.twig', [
            'film' => $film,
            'seen' => in_array($slug, $seenFilmService->getSeenFilmSlugs($user->getUserIdentifier()), true),
            'unwanted' => in_array($slug, $unwantedFilmService->getUnwantedFilmSlugs($user->getUserIdentifier()), true),
        ]);
    }
}
