<?php

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Account\UnwantedFilmService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Profile')]
class UnwantedFilmController extends AbstractController
{
    public function __construct(private UnwantedFilmService $unwantedFilmService)
    {
    }

    #[Route('/api/me/unwanted-films', name: 'api_unwanted_films', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Films the user does not want to see: slug and title, sorted by title')]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $films = [];
        foreach ($this->unwantedFilmService->listUnwantedFilms($user->getUserIdentifier()) as $film) {
            $films[] = ['slug' => $film['slug'], 'title' => $film['title']];
        }

        return new JsonResponse($films);
    }

    #[Route('/api/me/unwanted-films/{slug}', name: 'api_unwanted_film_put', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Film never offered again by the planner')]
    #[OA\Response(response: 404, description: 'Unknown film')]
    public function markUnwanted(string $slug, #[CurrentUser] User $user): Response
    {
        if (!$this->unwantedFilmService->markUnwanted($user->getUserIdentifier(), $slug)) {
            throw new NotFoundHttpException('Unknown film.');
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/me/unwanted-films/{slug}', name: 'api_unwanted_film_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Film offered again by the planner')]
    public function unmarkUnwanted(string $slug, #[CurrentUser] User $user): Response
    {
        $this->unwantedFilmService->unmarkUnwanted($user->getUserIdentifier(), $slug);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
