<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Api\Response\CityResource;
use App\Api\Response\Responder;
use App\Catalog\Repository\CityRepository;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Catalog')]
class CityController extends AbstractController
{
    #[Route('/api/cities', name: 'api_cities', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Synchronized cities: slug and name', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: CityResource::class))))]
    public function list(CityRepository $cityRepository, Responder $responder): JsonResponse
    {
        return $responder->json(array_map(CityResource::fromRow(...), $cityRepository->findAllForSelect()));
    }
}
