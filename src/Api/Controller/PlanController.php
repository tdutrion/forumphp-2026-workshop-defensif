<?php

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Catalog\Repository\CityRepository;
use App\Catalog\Repository\ShowtimeRepository;
use App\Planner\PlannerService;
use App\Web\Form\PlanType;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Planning')]
class PlanController extends AbstractController
{
    public function __construct(
        private PlannerService $plannerService,
        private CityRepository $cityRepository,
        private ShowtimeRepository $showtimeRepository,
        #[Autowire('%app.chains%')]
        private array $chains,
    ) {
    }

    #[Route('/api/plans', name: 'api_plans', methods: ['GET'])]
    #[OA\Parameter(name: 'date', in: 'query', required: true, description: 'Local day of the cinemas, in Y-m-d format', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'city', in: 'query', required: false, description: 'City slug (or position)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'position', in: 'query', required: false, description: 'JSON position, e.g. {"lat": 47.32, "lng": 5.04}', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'radius', in: 'query', required: false, description: 'Radius in km (1 to 50, default 10)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'films', in: 'query', required: false, description: 'Number of films (2 to 5, default 3)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'version', in: 'query', required: false, description: 'vf, vost, vo or vfst', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'acceptAds', in: 'query', required: false, description: '1 to accept arriving during the ads (15 minutes)', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Proposed programmes (at most 3), and the reason if there are fewer')]
    #[OA\Response(response: 422, description: 'Invalid parameters')]
    public function plan(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $form = $this->createForm(PlanType::class, null, [
            'dates' => $this->showtimeRepository->findAvailableDates($this->earliestLocalToday()),
            'cities' => $this->cityRepository->findAllForSelect(),
        ]);
        // false: missing parameters keep their default value (radius 10, 3 films).
        $form->submit($request->query->all(), false);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = ['field' => $error->getOrigin()?->getName() ?? '', 'message' => $error->getMessage()];
            }

            return new JsonResponse(
                ['type' => 'about:blank', 'title' => 'Invalid parameters', 'status' => 422, 'errors' => $errors],
                422,
                ['Content-Type' => 'application/problem+json'],
            );
        }

        $result = $this->plannerService->planFromForm($form->getData(), $user->getUserIdentifier());
        if (false === $result) {
            return new JsonResponse(['programmes' => [], 'reason' => 'no_showtime']);
        }

        $programmes = [];
        foreach ($result['programmes'] as $programme) {
            $showtimes = [];
            foreach ($programme['showtimes'] as $showtime) {
                $showtimes[] = [
                    'id' => $showtime['id'],
                    'film' => ['slug' => $showtime['filmSlug'], 'title' => $showtime['filmTitle']],
                    'cinema' => ['slug' => $showtime['cinemaSlug'], 'name' => $showtime['cinemaName']],
                    // ISO 8601 in the cinema's time zone: the offset lets a mobile app convert it.
                    'startsAt' => $this->localIso($showtime['startsAt'], $showtime['timezone']),
                    'endsAt' => $this->localIso($showtime['endsAt'], $showtime['timezone']),
                    'version' => $showtime['version'],
                    'lateMinutes' => $showtime['lateMinutes'],
                    'bookingUrl' => $showtime['bookingUrl'],
                ];
            }
            $programmes[] = ['wait' => $programme['wait'], 'distance' => $programme['distance'], 'showtimes' => $showtimes];
        }

        return new JsonResponse(['programmes' => $programmes, 'reason' => $result['reason']]);
    }

    /**
     * Showtime days are local days: "today" is the earliest current day among the chains' time zones.
     */
    private function earliestLocalToday(): string
    {
        $days = array_map(
            static fn (array $chain) => (new \DateTimeImmutable('now', new \DateTimeZone($chain['timezone'])))->format('Y-m-d'),
            $this->chains,
        );

        return min($days);
    }

    private function localIso(string $utc, string $timezone): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($timezone))->format(\DATE_ATOM);
    }
}
