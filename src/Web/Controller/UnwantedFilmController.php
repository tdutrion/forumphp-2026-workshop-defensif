<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\UnwantedFilmService;
use App\Web\PostRedirectGet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class UnwantedFilmController extends AbstractController
{
    public function __construct(
        private UnwantedFilmService $unwantedFilmService,
        private PostRedirectGet $postRedirectGet,
    ) {
    }

    #[Route('/films/{slug}/unwanted', name: 'app_film_unwanted', methods: ['POST'])]
    public function markUnwanted(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        if (!$this->unwantedFilmService->markUnwanted($user->getUserIdentifier(), $slug)) {
            throw $this->createNotFoundException('error.film_not_found');
        }

        return $this->respond($request, $slug);
    }

    #[Route('/films/{slug}/wanted', name: 'app_film_wanted', methods: ['POST'])]
    public function unmarkUnwanted(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        $this->unwantedFilmService->unmarkUnwanted($user->getUserIdentifier(), $slug);

        return $this->respond($request, $slug);
    }

    private function denyUnlessValidToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('unwanted-film', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('error.csrf_invalid');
        }
    }

    /**
     * From a list or a search (hidden "_return_route" and "_return" fields): Post/Redirect/Get back to it, now updated.
     * Otherwise: the film page. Always a Post/Redirect/Get, never a partial (AJAX) answer.
     */
    private function respond(Request $request, string $slug): Response
    {
        $back = $this->postRedirectGet->back($request);
        if (null !== $back) {
            return $back;
        }

        return $this->redirectToRoute('app_film_show', ['slug' => $slug], Response::HTTP_SEE_OTHER);
    }
}
