<?php

namespace App\Api\Controller;

use App\Account\Entity\User;
use App\Planner\PlanFailure;
use App\Planner\PlannerService;
use App\Planner\PlanRequest;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'Planning')]
class PlanController extends AbstractController
{
    public function __construct(
        private PlannerService $plannerService,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/api/plans', name: 'api_plans', methods: ['GET'])]
    #[OA\Parameter(name: 'date', in: 'query', required: true, description: 'Local day of the cinemas, in Y-m-d format', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'from', in: 'query', required: false, description: 'Earliest start, local time of the cinema (H:i)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'until', in: 'query', required: false, description: 'Latest end, local time (H:i), after from: the range stays within the day', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'city', in: 'query', required: false, description: 'City slug (or position)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'position', in: 'query', required: false, description: 'JSON position, e.g. {"lat": 47.32, "lng": 5.04}', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'films', in: 'query', required: false, description: 'Number of films (1 to 8, default 2)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'version', in: 'query', required: false, description: 'vf, vost, vo or vfst; vost and vo also include the films made in the language of the cinema (French films at Pathé)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'travelMode', in: 'query', required: false, description: 'walking, cycling, transit (default) or car: sets the travel time between two cinemas', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'acceptAds', in: 'query', required: false, description: '1 to accept arriving during the ads (15 minutes)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'seed', in: 'query', required: false, description: 'Draw of the programmes among the best ones (0 to 999999999): the same seed gives the same programmes; drawn when missing', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Proposed programmes (at most 3), the number of films per programme (fewer than asked when reason is fewer_films), the reason if there are fewer, and the seed of the draw')]
    #[OA\Response(response: 422, description: 'Invalid parameters')]
    public function plan(
        // Invalid parameters, unknown ones included, are a 422 (the default is a 404), rendered by ApiExceptionListener.
        #[MapQueryString(
            serializationContext: [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false, DenormalizerInterface::COLLECT_EXTRA_ATTRIBUTES_ERRORS => true],
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        PlanRequest $request,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $result = $this->plannerService->plan($request, $user->getUserIdentifier());
        if (PlanFailure::UnknownLocation === $result->failure) {
            // The position could not be read, or the city has no open cinema left: invalid input, like the other parameters.
            return new JsonResponse(
                ['type' => 'about:blank', 'title' => $this->translator->trans('api.invalid_parameters'), 'status' => 422, 'errors' => [['field' => null === $request->city ? 'position' : 'city', 'message' => $this->translator->trans('planner.result.unknown_place')]]],
                422,
                ['Content-Type' => 'application/problem+json'],
            );
        }
        if (PlanFailure::NoShowtime === $result->failure) {
            return new JsonResponse(['programmes' => [], 'reason' => $result->reason()]);
        }

        $programmes = [];
        foreach ($result->programmes as $programme) {
            $showtimes = [];
            foreach ($programme['showtimes'] as $showtime) {
                $showtimes[] = [
                    'id' => $showtime['id'],
                    'film' => ['slug' => $showtime['filmSlug'], 'title' => $showtime['filmTitle']],
                    'cinema' => ['slug' => $showtime['cinemaSlug'], 'name' => $showtime['cinemaName']],
                    // ISO 8601 in the cinema's time zone: the offset lets a mobile app convert it.
                    'startsAt' => $showtime['start']->localTime($showtime['timezone'])->format(\DATE_ATOM),
                    'endsAt' => $showtime['end']->localTime($showtime['timezone'])->format(\DATE_ATOM),
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

        return new JsonResponse(['programmes' => $programmes, 'reason' => $result->reason(), 'films' => $result->films, 'seed' => $result->seed]);
    }
}
