<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Catalog\CatalogCalendar;
use App\Catalog\Repository\CityRepository;
use App\Planner\PlannerService;
use App\Web\Form\PlanType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(PlanType::class, null, [
            'dates' => $this->calendar->availableDates(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
            'cities' => $this->cityRepository->findAllForSelect(),
        ]);
        $form->handleRequest($request);

        $result = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->plannerService->planFromForm($form->getData(), $user->getUserIdentifier());
        }

        // Every film and every cinema of the proposed programmes, once, in order of appearance.
        $proposedFilms = [];
        $proposedCinemas = [];
        foreach (false === $result || null === $result ? [] : $result['programmes'] as $programme) {
            foreach ($programme['showtimes'] as $showtime) {
                $proposedFilms[$showtime['filmSlug']] ??= ['slug' => $showtime['filmSlug'], 'title' => $showtime['filmTitle']];
                $proposedCinemas[$showtime['cinemaSlug']] ??= ['slug' => $showtime['cinemaSlug'], 'name' => $showtime['cinemaName']];
            }
        }

        return $this->render('home/index.html.twig', [
            'form' => $form,
            'result' => $result,
            'proposedFilms' => array_values($proposedFilms),
            'proposedCinemas' => array_values($proposedCinemas),
        ]);
    }
}
