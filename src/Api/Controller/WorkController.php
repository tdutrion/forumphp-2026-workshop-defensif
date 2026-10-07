<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Catalog')]
class WorkController extends AbstractController
{
    /** Any UUID: the works made by the migration for the films that existed before are not v7. */
    private const string ANY_UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    #[Route('/api/works/{id}', name: 'api_work', methods: ['GET'], requirements: ['id' => self::ANY_UUID])]
    #[OA\Response(response: 200, description: 'A work common to every chain: its external ids (null when unknown) and its films by chain')]
    #[OA\Response(response: 404, description: 'Unknown work')]
    public function show(string $id, EntityManagerInterface $em): JsonResponse
    {
        $work = $em->find(Work::class, Uuid::fromString($id));
        if (null === $work) {
            throw new NotFoundHttpException('error.work_not_found');
        }

        $films = array_map(
            static fn (Film $film): array => ['chain' => $film->getChain(), 'slug' => $film->getSlug(), 'title' => $film->getTitle()],
            $em->getRepository(Film::class)->findBy(['work' => $work], ['chain' => 'ASC', 'slug' => 'ASC']),
        );

        return new JsonResponse([
            'id' => $work->getId()->toRfc4122(),
            'originalTitle' => $work->getOriginalTitle(),
            'year' => $work->getYear(),
            'wikidataId' => $work->getWikidataId(),
            'imdbId' => $work->getImdbId(),
            'tmdbId' => $work->getTmdbId(),
            'films' => $films,
        ]);
    }
}
