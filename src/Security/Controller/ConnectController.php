<?php

declare(strict_types=1);

namespace App\Security\Controller;

use App\Account\AccountService;
use App\Account\Entity\User;
use App\Security\OAuthProviders;
use App\Security\UserInfoProviders;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

class ConnectController extends AbstractController
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private OAuthProviders $providers,
        private UserInfoProviders $userInfoProviders,
        private AccountService $accountService,
    ) {
    }

    #[Route('/connect/{provider}', name: 'app_connect_start', methods: ['GET'])]
    public function start(string $provider, Request $request): Response
    {
        if (!in_array($provider, $this->providers->enabled(), true)) {
            throw $this->createNotFoundException('security.unknown_provider');
        }

        if ($request->query->getBoolean('link') && null !== $this->getUser()) {
            $request->getSession()->set('oauth_link', true);
        } else {
            $request->getSession()->remove('oauth_link');
        }

        return $this->clientRegistry->getClient($provider)->redirect($this->userInfoProviders->for($provider)->scopes(), []);
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
            throw $this->createNotFoundException('security.unknown_provider');
        }

        $client = $this->clientRegistry->getClient($provider);
        try {
            $userInfo = $this->userInfoProviders->for($provider)->userInfo($client, $client->getAccessToken());
        } catch (\RuntimeException|\LogicException|IdentityProviderException|ClientExceptionInterface) {
            $this->addFlash('error', t('security.linking_failed'));

            return $this->redirectToRoute('app_settings');
        }

        if ($this->accountService->linkProvider($user, $provider, $userInfo)) {
            $this->addFlash('success', t('security.account_linked', ['%provider%' => $provider]));
        } else {
            $this->addFlash('error', t('security.account_linked_elsewhere', ['%provider%' => $provider]));
        }

        return $this->redirectToRoute('app_settings');
    }
}
