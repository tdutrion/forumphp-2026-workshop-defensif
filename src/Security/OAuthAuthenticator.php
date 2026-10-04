<?php

namespace App\Security;

use App\Account\AccountService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sign-in through any provider configured in knpu_oauth2_client.yaml.
 */
class OAuthAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private OAuthProviders $providers,
        private OAuthUserInfoExtractor $extractor,
        private AccountService $accountService,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if ('app_connect_check' !== $request->attributes->get('_route')) {
            return false;
        }

        // Linking an additional account is handled by ConnectController::check().
        $linking = $request->hasSession() && true === $request->getSession()->get('oauth_link');

        return !($linking && null !== $this->security->getUser());
    }

    public function authenticate(Request $request): Passport
    {
        $provider = (string) $request->attributes->get('provider');
        if (!in_array($provider, $this->providers->enabled(), true)) {
            throw new CustomUserMessageAuthenticationException('Unknown sign-in provider.');
        }

        $client = $this->clientRegistry->getClient($provider);
        try {
            $accessToken = $this->fetchAccessToken($client);
        } catch (\LogicException) {
            // No PKCE verifier in the session: this browser did not start the sign-in (reloaded or shared URL).
            throw new CustomUserMessageAuthenticationException('Sign-in failed. Please try again.');
        } catch (ClientExceptionInterface|\UnexpectedValueException) {
            // Network failure or unreadable answer during the token exchange.
            throw new CustomUserMessageAuthenticationException('The sign-in provider is not responding. Please try again.');
        }

        try {
            $userInfo = $this->extractor->extract($provider, $client, $accessToken);
        } catch (IdentityProviderException|ClientExceptionInterface|\UnexpectedValueException) {
            throw new CustomUserMessageAuthenticationException('The sign-in provider is not responding. Please try again.');
        }

        $user = $this->accountService->loginWithProvider($provider, $userInfo);

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // The target path is recorded by Symfony from an internal URL of the application.
        $target = $this->getTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($target ?? $this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessageKey()
            : 'Sign-in failed. Please try again.';

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', $this->translator->trans($message));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
