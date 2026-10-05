<?php

namespace App\Web\Controller;

use App\Account\AccountService;
use App\Account\ApiTokenService;
use App\Account\Entity\User;
use App\Account\ExcludedCinemaService;
use App\Account\Theme;
use App\Security\OAuthProviders;
use App\Web\PostRedirectGet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Settings of the account: excluded cinemas, connections, API tokens and colour theme.
 */
#[Route('/settings')]
class SettingsController extends AbstractController
{
    public function __construct(
        private AccountService $accountService,
        private ApiTokenService $apiTokenService,
        private OAuthProviders $oauthProviders,
        private ExcludedCinemaService $excludedCinemaService,
        private TranslatorInterface $translator,
        private PostRedirectGet $postRedirectGet,
    ) {
    }

    #[Route('', name: 'app_settings', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->renderSettings($user, null);
    }

    #[Route('/connections/{id}/remove', name: 'app_settings_connection_remove', methods: ['POST'])]
    public function removeConnection(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('profile-connection', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('error.csrf_invalid');
        }

        if (!$this->accountService->removeLinkedAccount($user, $id)) {
            $this->addFlash('error', $this->translator->trans('settings.cannot_remove_connection'));
        }

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/tokens', name: 'app_settings_token_create', methods: ['POST'])]
    public function createToken(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('api-token', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('error.csrf_invalid');
        }

        $name = trim($request->request->getString('name'));
        if ('' === $name || mb_strlen($name) > 100) {
            $this->addFlash('error', $this->translator->trans('settings.token_name_invalid'));

            return $this->redirectToRoute('app_settings');
        }

        // No redirect: the plain token is shown only once and is never stored in the session.
        // The price: reloading this page resubmits the form (the browser asks first) and creates another token.
        return $this->renderSettings($user, $this->apiTokenService->create($user, $name));
    }

    #[Route('/tokens/{id}/revoke', name: 'app_settings_token_revoke', methods: ['POST'])]
    public function revokeToken(string $id, Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('api-token', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('error.csrf_invalid');
        }

        if (!$this->apiTokenService->revoke($user, $id)) {
            $this->addFlash('error', $this->translator->trans('settings.token_not_found'));
        }

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/theme', name: 'app_settings_theme', methods: ['POST'])]
    public function changeTheme(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('settings-theme', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('error.csrf_invalid');
        }

        $theme = Theme::tryFrom($request->request->getString('theme'));
        if (null === $theme) {
            throw new UnprocessableEntityHttpException('error.theme_invalid');
        }
        $this->accountService->changeTheme($user, $theme);

        // From the switch of the top menu: back to the page it was on.
        return $this->postRedirectGet->back($request) ?? $this->redirectToRoute('app_settings', [], Response::HTTP_SEE_OTHER);
    }

    private function renderSettings(User $user, #[\SensitiveParameter] ?string $newToken): Response
    {
        $linkedProviders = [];
        foreach ($user->getLinkedAccounts() as $linkedAccount) {
            $linkedProviders[] = $linkedAccount->getProvider();
        }

        return $this->render('settings/index.html.twig', [
            'user' => $user,
            'excludedCinemas' => $this->excludedCinemaService->listExcludedCinemas($user->getUserIdentifier()),
            'providersToLink' => array_values(array_diff($this->oauthProviders->enabled(), $linkedProviders)),
            'tokens' => $this->apiTokenService->listForUser($user),
            'newToken' => $newToken,
        ]);
    }
}
