<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\UnwantedFilmService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\UX\Turbo\TurboBundle;

class UnwantedFilmController extends AbstractController
{
    public function __construct(private UnwantedFilmService $unwantedFilmService)
    {
    }

    #[Route('/films/{slug}/unwanted', name: 'app_film_unwanted', methods: ['POST'])]
    public function markUnwanted(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        if (!$this->unwantedFilmService->markUnwanted($user->getUserIdentifier(), $slug)) {
            throw $this->createNotFoundException('Unknown film.');
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
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * With Turbo: a stream that replaces every button of the film.
     * Without JavaScript: back to the film page.
     */
    private function respond(Request $request, string $slug, bool $unwanted): Response
    {
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('unwanted/toggle.stream.html.twig', ['slug' => $slug, 'unwanted' => $unwanted]);
        }

        return $this->redirectToRoute('app_film_show', ['slug' => $slug]);
    }
}
