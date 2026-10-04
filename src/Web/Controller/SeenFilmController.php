<?php

namespace App\Web\Controller;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\UX\Turbo\TurboBundle;

class SeenFilmController extends AbstractController
{
    public function __construct(private SeenFilmService $seenFilmService)
    {
    }

    #[Route('/films/{slug}/seen', name: 'app_film_seen', methods: ['POST'])]
    public function markSeen(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        if (!$this->seenFilmService->markSeen($user->getUserIdentifier(), $slug)) {
            throw $this->createNotFoundException('Unknown film.');
        }

        return $this->respond($request, [$slug], true);
    }

    #[Route('/films/{slug}/unseen', name: 'app_film_unseen', methods: ['POST'])]
    public function unmarkSeen(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        $this->seenFilmService->unmarkSeen($user->getUserIdentifier(), $slug);

        return $this->respond($request, [$slug], false);
    }

    #[Route('/programmes/seen', name: 'app_programme_seen', methods: ['POST'])]
    public function markProgrammeSeen(Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        $slugs = array_values(array_filter($request->request->all('films'), 'is_string'));
        $this->seenFilmService->markAllSeen($user->getUserIdentifier(), $slugs);

        return $this->respond($request, $slugs, true);
    }

    private function denyUnlessValidToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('seen-film', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * With Turbo: a stream that replaces the buttons of all the films concerned.
     * Without JavaScript: back to the film page (or to the profile for a programme).
     */
    private function respond(Request $request, array $slugs, bool $seen): Response
    {
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('seen/toggle.stream.html.twig', ['slugs' => $slugs, 'seen' => $seen]);
        }

        if (1 === \count($slugs)) {
            return $this->redirectToRoute('app_film_show', ['slug' => $slugs[0]]);
        }

        return $this->redirectToRoute('app_profile');
    }
}
