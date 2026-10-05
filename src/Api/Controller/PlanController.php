<?php

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Catalog\CatalogCalendar;
use App\Catalog\Repository\CityRepository;
use App\Planner\PlannerService;
use App\Web\Form\PlanType;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private CatalogCalendar $calendar,
    ) {
    }

    #[Route('/api/plans', name: 'api_plans', methods: ['GET'])]
    #[OA\Parameter(name: 'date', in: 'query', required: true, description: 'Local day of the cinemas, in Y-m-d format', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'from', in: 'query', required: false, description: 'Earliest start, local time of the cinema (H:i)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'until', in: 'query', required: false, description: 'Latest end, local time (H:i), after from: the range stays within the day', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'city', in: 'query', required: false, description: 'City slug (or position)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'position', in: 'query', required: false, description: 'JSON position, e.g. {"lat": 47.32, "lng": 5.04}', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'films', in: 'query', required: false, description: 'Number of films (1 to 5, default 2)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'version', in: 'query', required: false, description: 'vf, vost, vo or vfst; vost and vo also include the films made in the language of the cinema (French films at Pathé)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'travelMode', in: 'query', required: false, description: 'walking, cycling, transit (default) or car: sets the travel time between two cinemas', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'acceptAds', in: 'query', required: false, description: '1 to accept arriving during the ads (15 minutes)', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Proposed programmes (at most 3), the number of films per programme (fewer than asked when reason is fewer_films), and the reason if there are fewer')]
    #[OA\Response(response: 422, description: 'Invalid parameters')]
    public function plan(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $form = $this->createForm(PlanType::class, null, [
            'dates' => $this->calendar->availableDates(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
            'cities' => $this->cityRepository->findAllForSelect(),
        ]);
        // false: missing parameters keep their default value (2 films, public transport).
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

        $data = $form->getData();
        $result = $this->plannerService->planFromForm($data, $user->getUserIdentifier());
        if (false !== $result && 'unknown_location' === $result['reason'] && empty($data['city'])) {
            // The position could not be read: invalid input, like the other parameters.
            return new JsonResponse(
                ['type' => 'about:blank', 'title' => 'Invalid parameters', 'status' => 422, 'errors' => [['field' => 'position', 'message' => 'Unknown place: choose a city from the list or allow geolocation.']]],
                422,
                ['Content-Type' => 'application/problem+json'],
            );
        }
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
                    // Minutes from the end of the previous film to this showtime, and the travel among them.
                    'breakMinutes' => $showtime['breakMinutes'],
                    'travelMinutes' => $showtime['travelMinutes'],
                    'bookingUrl' => $showtime['bookingUrl'],
                ];
            }
            $programmes[] = [
                // wait = minutes really lost waiting (breaks minus the 10-minute margins and the travel): the ranking score.
                'wait' => $programme['wait'],
                'breakMinutes' => $programme['breakMinutes'],
                'travelMinutes' => $programme['travelMinutes'],
                'distance' => $programme['distance'],
                'showtimes' => $showtimes,
            ];
        }

        return new JsonResponse(['programmes' => $programmes, 'reason' => $result['reason'], 'films' => $result['films']]);
    }

    private function localIso(string $utc, string $timezone): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($timezone))->format(\DATE_ATOM);
    }
}
