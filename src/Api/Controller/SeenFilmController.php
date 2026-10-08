<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Profile')]
class SeenFilmController extends AbstractController
{
    public function __construct(private SeenFilmService $seenFilmService)
    {
    }

    #[Route('/api/me/seen-films', name: 'api_seen_films', methods: ['GET'])]
    #[OA\Parameter(name: 'order', in: 'query', required: false, description: 'asc (default) or desc: sort by title', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Films already seen: slug and title')]
    #[OA\Response(response: 400, description: 'Invalid sort order')]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $order = $request->query->getString('order', 'asc');
        if ('asc' !== $order && 'desc' !== $order) {
            throw new BadRequestHttpException('error.order_invalid');
        }

        $films = [];
        foreach ($this->seenFilmService->listSeenFilms($user->getUserIdentifier(), $order) as $film) {
            $films[] = ['slug' => $film['slug'], 'title' => $film['title']];
        }

        return new JsonResponse($films);
    }

    #[Route('/api/me/seen-films/{slug}', name: 'api_seen_film_put', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Film marked as seen')]
    #[OA\Response(response: 404, description: 'Unknown film')]
    public function markSeen(string $slug, #[CurrentUser] User $user): Response
    {
        if (!$this->seenFilmService->markSeen($user->getUserIdentifier(), $slug)) {
            throw new NotFoundHttpException('error.film_not_found');
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/me/seen-films/{slug}', name: 'api_seen_film_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Film removed from the list')]
    public function unmarkSeen(string $slug, #[CurrentUser] User $user): Response
    {
        $this->seenFilmService->unmarkSeen($user->getUserIdentifier(), $slug);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
