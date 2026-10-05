<?php

namespace App\Security\Controller;

use App\Account\AccountService;
use App\Account\Entity\User;
use App\Security\OAuthProviders;
use App\Security\OAuthUserInfoExtractor;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConnectController extends AbstractController
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private OAuthProviders $providers,
        private OAuthUserInfoExtractor $extractor,
        private AccountService $accountService,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/connect/{provider}', name: 'app_connect_start', methods: ['GET'])]
    public function start(string $provider, Request $request): Response
    {
        if (!in_array($provider, $this->providers->enabled(), true)) {
            throw $this->createNotFoundException('Unknown sign-in provider.');
        }

        if ($request->query->getBoolean('link') && null !== $this->getUser()) {
            $request->getSession()->set('oauth_link', true);
        } else {
            $request->getSession()->remove('oauth_link');
        }

        return $this->clientRegistry->getClient($provider)->redirect($this->providers->scopes($provider), []);
    }

    /**
     * Reached only to link an additional account: the sign-in itself goes through OAuthAuthenticator.
     */
    #[Route('/connect/{provider}/check', name: 'app_connect_check', methods: ['GET'])]
    public function check(string $provider, Request $request): Response
    {
        $user = $this->getUser();
        if (true !== $request->getSession()->remove('oauth_link') || !$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (!in_array($provider, $this->providers->enabled(), true)) {
            throw $this->createNotFoundException('Unknown sign-in provider.');
        }

        $client = $this->clientRegistry->getClient($provider);
        try {
            $userInfo = $this->extractor->extract($provider, $client, $client->getAccessToken());
        } catch (\RuntimeException|\LogicException|IdentityProviderException|ClientExceptionInterface) {
            $this->addFlash('error', $this->translator->trans('security.linking_failed'));

            return $this->redirectToRoute('app_profile');
        }

        if ($this->accountService->linkProvider($user, $provider, $userInfo)) {
            $this->addFlash('success', $this->translator->trans('security.account_linked', ['%provider%' => $provider]));
        } else {
            $this->addFlash('error', $this->translator->trans('security.account_linked_elsewhere', ['%provider%' => $provider]));
        }

        return $this->redirectToRoute('app_profile');
    }
}
