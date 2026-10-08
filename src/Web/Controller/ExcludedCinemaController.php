<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\ExcludedCinemaService;
use App\Web\PostRedirectGet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ExcludedCinemaController extends AbstractController
{
    public function __construct(
        private ExcludedCinemaService $excludedCinemaService,
        private PostRedirectGet $postRedirectGet,
    ) {
    }

    #[Route('/cinemas/{slug}/exclude', name: 'app_cinema_exclude', methods: ['POST'])]
    public function exclude(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        if (!$this->excludedCinemaService->exclude($user->getUserIdentifier(), $slug)) {
            throw $this->createNotFoundException('error.cinema_not_found');
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
            throw $this->createAccessDeniedException('error.csrf_invalid');
        }
    }

    /**
     * From a list or a search (hidden "_return_route" and "_return" fields): Post/Redirect/Get back to it, now updated.
     * Otherwise: back to the settings.
     */
    private function respond(Request $request): Response
    {
        $back = $this->postRedirectGet->back($request);
        if (null !== $back) {
            return $back;
        }

        return $this->redirectToRoute('app_settings', [], Response::HTTP_SEE_OTHER);
    }
}
