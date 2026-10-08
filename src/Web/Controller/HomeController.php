<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Catalog\CatalogCalendar;
use App\Catalog\CinemaChainRegistry;
use App\Catalog\Repository\CityRepository;
use App\Planner\PlannerService;
use App\Web\Form\PlanType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class HomeController extends AbstractController
{
    public function __construct(
        private PlannerService $plannerService,
        private CityRepository $cityRepository,
        private CatalogCalendar $calendar,
        private ClockInterface $clock,
        private CinemaChainRegistry $chains,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] ?User $user): Response
    {
        // Same address for everyone: visitors discover the service, signed-in users plan.
        if (null === $user) {
            return $this->render('landing/index.html.twig', [
                'supportedChains' => $this->chains->supported(),
                'plannedChains' => $this->chains->planned(),
            ]);
        }

        $form = $this->createForm(PlanType::class, null, [
            'dates' => $this->calendar->availableDates($this->clock->now()),
            'cities' => $this->cityRepository->findAllForSelect(),
        ]);
        $form->handleRequest($request);

        $result = null;
        $nearbyCinemas = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->plannerService->plan($form->getData(), $user->getUserIdentifier());
            $nearbyCinemas = $this->plannerService->nearbyCinemas($form->getData(), $user->getUserIdentifier());
        }

        // Every film and every cinema of the proposed programmes, once, in order of appearance.
        $proposedFilms = [];
        $usedCinemas = [];
        foreach ($result->programmes ?? [] as $programme) {
            foreach ($programme->showtimes as $showtime) {
                $proposedFilms[$showtime->filmSlug] ??= ['slug' => $showtime->filmSlug, 'title' => $showtime->filmTitle];
                $usedCinemas[$showtime->cinemaSlug] = true;
            }
        }

        // The buttons of the page come back to the same draw; "Other programmes" draws again.
        $seed = $result?->seed;
        $query = $request->query->all();
        $returnQuery = $request->getQueryString();
        $otherProgrammesQuery = null;
        if (null !== $seed && \is_array($query['plan'] ?? null)) {
            $returnQuery = http_build_query(array_replace_recursive($query, ['plan' => ['seed' => $seed]]));
            $otherProgrammesQuery = array_replace_recursive($query, ['plan' => ['seed' => random_int(0, PlannerService::MAX_SEED)]]);
        }

        return $this->render('home/index.html.twig', [
            'form' => $form,
            'seed' => $seed,
            'returnQuery' => $returnQuery,
            'otherProgrammesQuery' => $otherProgrammesQuery,
            'cityCentres' => $this->cityRepository->findCentres(),
            'result' => $result,
            'proposedFilms' => array_values($proposedFilms),
            'nearbyCinemas' => $nearbyCinemas,
            'usedCinemas' => $usedCinemas,
        ]);
    }
}
