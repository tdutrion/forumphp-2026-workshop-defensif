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
use Symfony\UX\Turbo\TurboBundle;

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

        return $this->respond($request, $slug, true);
    }

    #[Route('/films/{slug}/wanted', name: 'app_film_wanted', methods: ['POST'])]
    public function unmarkUnwanted(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        $this->unwantedFilmService->unmarkUnwanted($user->getUserIdentifier(), $slug);

        return $this->respond($request, $slug, false);
    }

    private function denyUnlessValidToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('unwanted-film', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('error.csrf_invalid');
        }
    }

    /**
     * From a list or a search (hidden "_return_route" and "_return" fields): Post/Redirect/Get back to it, now updated.
     * Otherwise, with Turbo: a stream that replaces every button of the film; without JavaScript: the film page.
     */
    private function respond(Request $request, string $slug, bool $unwanted): Response
    {
        $back = $this->postRedirectGet->back($request);
        if (null !== $back) {
            return $back;
        }

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('unwanted/toggle.stream.html.twig', ['slug' => $slug, 'unwanted' => $unwanted]);
        }

        return $this->redirectToRoute('app_film_show', ['slug' => $slug]);
    }
}
