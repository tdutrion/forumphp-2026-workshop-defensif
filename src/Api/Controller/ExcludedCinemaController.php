<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Account\ExcludedCinemaService;
use App\Api\Response\CinemaResource;
use App\Api\Response\Responder;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Profile')]
class ExcludedCinemaController extends AbstractController
{
    public function __construct(private ExcludedCinemaService $excludedCinemaService, private Responder $responder)
    {
    }

    #[Route('/api/me/excluded-cinemas', name: 'api_excluded_cinemas', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Cinemas the planner never uses for this user: slug and name, sorted by name', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: CinemaResource::class))))]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->responder->json(array_map(CinemaResource::fromRow(...), $this->excludedCinemaService->listExcludedCinemas($user->getUserIdentifier())));
    }

    #[Route('/api/me/excluded-cinemas/{slug}', name: 'api_excluded_cinema_put', methods: ['PUT'])]
    #[OA\Response(response: 204, description: 'Cinema never used again by the planner')]
    #[OA\Response(response: 404, description: 'Unknown cinema')]
    public function exclude(string $slug, #[CurrentUser] User $user): Response
    {
        if (!$this->excludedCinemaService->exclude($user->getUserIdentifier(), $slug)) {
            throw new NotFoundHttpException('error.cinema_not_found');
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/me/excluded-cinemas/{slug}', name: 'api_excluded_cinema_delete', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Cinema used again by the planner')]
    public function include(string $slug, #[CurrentUser] User $user): Response
    {
        $this->excludedCinemaService->include($user->getUserIdentifier(), $slug);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
