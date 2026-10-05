<?php

namespace App\Web\Controller;

use App\Account\AccountService;
use App\Account\ApiTokenService;
use App\Account\Entity\User;
use App\Account\ExcludedCinemaService;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Security\OAuthProviders;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    public function __construct(
        private SeenFilmService $seenFilmService,
        private AccountService $accountService,
        private ApiTokenService $apiTokenService,
        private OAuthProviders $oauthProviders,
        private UnwantedFilmService $unwantedFilmService,
        private ExcludedCinemaService $excludedCinemaService,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->renderProfile($user, null);
    }

    #[Route('/connections/{id}/remove', name: 'app_profile_connection_remove', methods: ['POST'])]
    public function removeConnection(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('profile-connection', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->accountService->removeLinkedAccount($user, $id)) {
            $this->addFlash('error', $this->translator->trans('profile.cannot_remove_connection'));
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/tokens', name: 'app_profile_token_create', methods: ['POST'])]
    public function createToken(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('api-token', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $name = trim($request->request->getString('name'));
        if ('' === $name || mb_strlen($name) > 100) {
            $this->addFlash('error', $this->translator->trans('profile.token_name_invalid'));

            return $this->redirectToRoute('app_profile');
        }

        // No redirect: the plain token is shown only once and is never stored in the session.
        // The price: reloading this page resubmits the form (the browser asks first) and creates another token.
        return $this->renderProfile($user, $this->apiTokenService->create($user, $name));
    }

    #[Route('/tokens/{id}/revoke', name: 'app_profile_token_revoke', methods: ['POST'])]
    public function revokeToken(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('api-token', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->apiTokenService->revoke($user, $id)) {
            $this->addFlash('error', $this->translator->trans('profile.token_not_found'));
        }

        return $this->redirectToRoute('app_profile');
    }

    private function renderProfile(User $user, #[\SensitiveParameter] ?string $newToken): Response
    {
        $linkedProviders = [];
        foreach ($user->getLinkedAccounts() as $linkedAccount) {
            $linkedProviders[] = $linkedAccount->getProvider();
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'seenFilms' => $this->seenFilmService->listSeenFilms($user->getUserIdentifier()),
            'unwantedFilms' => $this->unwantedFilmService->listUnwantedFilms($user->getUserIdentifier()),
            'excludedCinemas' => $this->excludedCinemaService->listExcludedCinemas($user->getUserIdentifier()),
            'providersToLink' => array_values(array_diff($this->oauthProviders->enabled(), $linkedProviders)),
            'tokens' => $this->apiTokenService->listForUser($user),
            'newToken' => $newToken,
        ]);
    }
}
