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

        return $this->respond($request, $slug, true);
    }

    #[Route('/films/{slug}/unseen', name: 'app_film_unseen', methods: ['POST'])]
    public function unmarkSeen(string $slug, Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyUnlessValidToken($request);
        $this->seenFilmService->unmarkSeen($user->getUserIdentifier(), $slug);

        return $this->respond($request, $slug, false);
    }

    private function denyUnlessValidToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('seen-film', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * From a search (hidden "_return" field): Post/Redirect/Get back to the same search, now updated.
     * Otherwise, with Turbo: a stream that replaces every button of the film; without JavaScript: the film page.
     */
    private function respond(Request $request, string $slug, bool $seen): Response
    {
        $return = $request->request->getString('_return');
        if ('' !== $return) {
            // Only the query of the planner page is taken back: no redirect to a URL chosen by the client.
            parse_str($return, $query);

            return $this->redirectToRoute('app_home', $query, Response::HTTP_SEE_OTHER);
        }

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('seen/toggle.stream.html.twig', ['slugs' => [$slug], 'seen' => $seen]);
        }

        return $this->redirectToRoute('app_film_show', ['slug' => $slug]);
    }
}
