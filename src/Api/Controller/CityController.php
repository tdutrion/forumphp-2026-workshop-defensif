<?php

namespace App\Api\Controller;

use App\Catalog\Repository\CityRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Catalog')]
class CityController extends AbstractController
{
    #[Route('/api/cities', name: 'api_cities', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Synchronized cities: slug and name')]
    public function list(CityRepository $cityRepository): JsonResponse
    {
        return new JsonResponse($cityRepository->findAllForSelect());
    }
}
