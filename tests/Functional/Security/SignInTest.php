<?php

namespace App\Tests\Functional\Security;

use App\Account\Entity\User;
use App\Account\Repository\LinkedAccountRepository;
use App\Tests\Builder\OAuthServerBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\Fake\FakeOAuthServer;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SignInTest extends WebTestCase
{
    use StoresEntities;

    public function testTheSignInPageOffersTheEnabledProviders(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/login');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/connect/local"]');
        self::assertSelectorExists('a[href="/connect/github"]');
        self::assertSelectorExists('a[href="/connect/google"]');
    }

    public function testTheProviderIsCalledWithAStateAndAPkceChallenge(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/connect/local');

        // Assert
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('http://localhost:8081/local/authorize?', $location);
        self::assertStringContainsString('state=', $location);
        self::assertStringContainsString('code_challenge=', $location);
        self::assertStringContainsString('code_challenge_method=S256', $location);
        self::assertStringContainsString(urlencode('/connect/local/check'), $location);
    }

    public function testAnUnknownProviderIsNotFound(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/connect/facebook');

        // Assert
        self::assertResponseStatusCodeSame(404);
    }

    public function testSigningInForTheFirstTimeCreatesTheAccount(): void
    {
        // Arrange
        $client = $this->browserFacing(OAuthServerBuilder::anOAuthServer()->withLocalUser('grace@example.org', 'grace@example.org'));

        // Act
        $this->signIn($client, 'local');

        // Assert
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorExists('form[action="/logout"]');
        self::assertSame('grace@example.org', $this->accountOf('local', 'grace@example.org')?->getDisplayName());
    }

    public function testTheLocalProviderNeverOpensAnExistingAccount(): void
    {
        // Arrange: anyone can type any name on the offline provider, so its emails prove nothing.
        $client = $this->browserFacing(OAuthServerBuilder::anOAuthServer()->withLocalUser('ada@example.org', 'ada@example.org'));
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->linkedTo('github', '42')->build();
        $this->store($ada);

        // Act
        $this->signIn($client, 'local');

        // Assert
        $signedIn = $this->accountOf('local', 'ada@example.org');
        self::assertNotNull($signedIn);
        self::assertNotSame($ada->getUserIdentifier(), $signedIn->getUserIdentifier());
    }

    public function testGithubSignInOpensTheAccountOfThePrimaryVerifiedEmail(): void
    {
        // Arrange
        $client = $this->browserFacing(OAuthServerBuilder::anOAuthServer()
            ->withGithubUser(42, 'ada', 'ada-public@example.org')
            ->withGithubEmail('old@example.org', primary: false, verified: true)
            ->withGithubEmail('ada@example.org', primary: true, verified: true));
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->build();
        $this->store($ada);

        // Act
        $this->signIn($client, 'github');

        // Assert
        self::assertResponseRedirects('/');
        self::assertSame($ada->getUserIdentifier(), $this->accountOf('github', '42')?->getUserIdentifier());
    }

    public function testAnUnverifiedGithubEmailDoesNotOpenTheAccountUsingIt(): void
    {
        // Arrange
        $client = $this->browserFacing(OAuthServerBuilder::anOAuthServer()
            ->withGithubUser(42, 'ada')
            ->withGithubEmail('ada@example.org', primary: true, verified: false));
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->build();
        $this->store($ada);

        // Act
        $this->signIn($client, 'github');

        // Assert
        $signedIn = $this->accountOf('github', '42');
        self::assertNotNull($signedIn);
        self::assertNotSame($ada->getUserIdentifier(), $signedIn->getUserIdentifier());
        self::assertNull($signedIn->getEmail());
    }

    public function testASignedInUserLinksAnotherProviderInsteadOfSwitchingAccount(): void
    {
        // Arrange
        $client = $this->browserFacing(OAuthServerBuilder::anOAuthServer()
            ->withGithubUser(42, 'grace')
            ->withGithubEmail('grace@example.org', primary: true, verified: true));
        $ada = UserBuilder::aUser()->withEmail('ada@example.org')->build();
        $this->store($ada);
        $client->loginUser($ada);

        // Act
        $this->signIn($client, 'github', '?link=1');

        // Assert
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'github account linked.');
        self::assertSame($ada->getUserIdentifier(), $this->accountOf('github', '42')?->getUserIdentifier());
    }

    public function testAForgedStateIsRefusedInTheLanguageOfTheBrowser(): void
    {
        // Arrange
        $client = $this->browserFacing(OAuthServerBuilder::anOAuthServer()->withLocalUser('grace@example.org', 'grace@example.org'));
        $client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr-FR,fr;q=0.9');
        $client->request('GET', '/connect/local');

        // Act
        $client->request('GET', '/connect/local/check', ['code' => 'a-code', 'state' => 'forged']);

        // Assert
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'La connexion a échoué. Réessayez.');
        self::assertNull($this->accountOf('local', 'grace@example.org'));
    }

    public function testACallbackThisBrowserNeverStartedSendsBackToTheSignInPage(): void
    {
        // Arrange
        $client = self::createClient();

        // Act: a reloaded or shared callback URL, with no sign-in in progress in this session.
        $client->request('GET', '/connect/local/check', ['code' => 'a-code', 'state' => 'a-state']);

        // Assert
        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'Sign-in failed. Please try again.');
    }

    public function testSigningOutNeedsTheFormToken(): void
    {
        // Arrange
        $client = self::createClient();
        $ada = UserBuilder::aUser()->build();
        $this->store($ada);
        $client->loginUser($ada);
        $logout = $client->request('GET', '/')->selectButton('Log out')->form();

        // Act
        $client->request('POST', '/logout');
        $withoutToken = $client->getResponse()->getStatusCode();
        $client->submit($logout);

        // Assert
        self::assertSame(403, $withoutToken);
        self::assertResponseRedirects('/login');
    }

    /**
     * A browser whose requests all reach the same kernel, so the fake provider keeps its answers.
     */
    private function browserFacing(OAuthServerBuilder $server): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->get(FakeOAuthServer::class)->serve($server);

        return $client;
    }

    /**
     * Goes to the provider, then comes back to the application with a code and the state it was given.
     */
    private function signIn(KernelBrowser $client, string $provider, string $query = ''): void
    {
        $client->request('GET', '/connect/'.$provider.$query);
        parse_str((string) parse_url((string) $client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $authorize);
        $client->request('GET', '/connect/'.$provider.'/check', ['code' => 'a-code', 'state' => $authorize['state'] ?? '']);
    }

    private function accountOf(string $provider, string $providerUserId): ?User
    {
        return self::getContainer()->get(LinkedAccountRepository::class)
            ->findOneByProviderIdentity($provider, $providerUserId)
            ?->getUser();
    }
}
