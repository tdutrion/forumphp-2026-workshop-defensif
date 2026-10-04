<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Catalog\Repository\CityRepository;
use App\Catalog\Repository\ShowtimeRepository;
use App\Planner\PlannerService;
use App\Web\Form\PlanType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class HomeController extends AbstractController
{
    public function __construct(
        private PlannerService $plannerService,
        private CityRepository $cityRepository,
        private ShowtimeRepository $showtimeRepository,
        #[Autowire('%app.chains%')]
        private array $chains,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(PlanType::class, null, [
            'dates' => $this->showtimeRepository->findAvailableDates($this->earliestLocalToday()),
            'cities' => $this->cityRepository->findAllForSelect(),
        ]);
        $form->handleRequest($request);

        $result = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->plannerService->planFromForm($form->getData(), $user->getUserIdentifier());
        }

        return $this->render('home/index.html.twig', [
            'form' => $form,
            'result' => $result,
        ]);
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
}
