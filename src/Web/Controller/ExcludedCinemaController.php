<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\ExcludedCinemaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ExcludedCinemaController extends AbstractController
{
    public function __construct(private ExcludedCinemaService $excludedCinemaService)
    {
    }

    #[Route('/cinemas/{slug}/exclude', name: 'app_cinema_exclude', methods: ['POST'])]
    public function exclude(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        if (!$this->excludedCinemaService->exclude($user->getUserIdentifier(), $slug)) {
            throw $this->createNotFoundException('Unknown cinema.');
        }

        return $this->respond($request);
    }

    #[Route('/cinemas/{slug}/include', name: 'app_cinema_include', methods: ['POST'])]
    public function include(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        $this->excludedCinemaService->include($user->getUserIdentifier(), $slug);

        return $this->respond($request);
    }

    private function denyUnlessValidToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('excluded-cinema', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * From a search (hidden "_return" field): Post/Redirect/Get back to the same search, now updated.
     * Otherwise (profile): back to the profile.
     */
    private function respond(Request $request): Response
    {
        $return = $request->request->getString('_return');
        if ('' !== $return) {
            // Only the query of the planner page is taken back: no redirect to a URL chosen by the client.
            parse_str($return, $query);

            return $this->redirectToRoute('app_home', $query, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_profile', [], Response::HTTP_SEE_OTHER);
    }
}
