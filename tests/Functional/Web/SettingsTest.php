<?php

namespace App\Tests\Functional\Web;

use App\Account\ApiTokenService;
use App\Account\Entity\User;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsTest extends WebTestCase
{
    use StoresEntities;

    private User $user;

    private function signedInAs(UserBuilder $user): KernelBrowser
    {
        $client = self::createClient();
        $this->user = $user->build();
        $this->store($this->user);
        $client->loginUser($this->user);

        return $client;
    }

    private function tokens(): ApiTokenService
    {
        return self::getContainer()->get(ApiTokenService::class);
    }

    public function testTheProfileMenuLeadsToTheSettingsTheHistoryTheUnwantedFilmsAndSigningOut(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());

        // Act
        $menu = $client->request('GET', '/settings')->filter('#profile-menu');

        // Assert
        self::assertSame(['/settings', '/history', '/not-for-me'], $menu->filter('a')->each(static fn ($link) => $link->attr('href')));
        self::assertSame('/logout', $menu->filter('form')->attr('action'));
    }

    public function testShowsTheConnectionsAndTheProvidersLeftToLink(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());

        // Act
        $client->request('GET', '/settings');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#connections', 'local');
        self::assertSelectorExists('#connections a[href="/connect/github?link=1"]');
        self::assertSelectorNotExists('#connections a[href="/connect/local?link=1"]');
    }

    public function testTheChosenThemeIsKeptOnEveryPage(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());
        $automaticByDefault = $client->request('GET', '/settings')->filter('html')->attr('data-theme');
        $form = $client->getCrawler()->filter('#theme form')->form(['theme' => 'dark']);

        // Act
        $client->submit($form);
        $redirect = $client->getResponse();
        $client->request('GET', '/');

        // Assert
        self::assertNull($automaticByDefault);
        self::assertSame(303, $redirect->getStatusCode());
        self::assertSelectorExists('html[data-theme="dark"]');
    }

    public function testTheSwitchOfTheTopMenuTogglesLightAndDarkAndStaysOnThePage(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());
        $this->store(FilmBuilder::aFilm()->withSlug('digger-51293')->titled('Digger')->build());
        $crawler = $client->request('GET', '/films/digger-51293');

        // Act
        $client->submit($crawler->filter('#theme-switch form')->form());
        $redirect = $client->getResponse();
        $dark = $client->followRedirect()->filter('html')->attr('data-theme');
        $client->submit($client->getCrawler()->filter('#theme-switch form')->form());
        $light = $client->followRedirect()->filter('html')->attr('data-theme');

        // Assert
        self::assertSame(303, $redirect->getStatusCode());
        self::assertSame('/films/digger-51293', $redirect->headers->get('Location'));
        self::assertSame('dark', $dark, 'from the automatic theme, without JavaScript: dark');
        self::assertSame('light', $light);
    }

    public function testAnUnknownThemeIsRefused(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());
        $token = $client->request('GET', '/settings')->filter('#theme input[name="_token"]')->attr('value');

        // Act
        $client->request('POST', '/settings/theme', ['theme' => 'neon', '_token' => $token]);

        // Assert
        self::assertResponseStatusCodeSame(422);
    }

    public function testANewTokenIsShownOnlyOnce(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());
        $form = $client->request('GET', '/settings')->selectButton('Create a token')->form(['name' => 'My app']);

        // Act
        $client->submit($form);
        $plain = $client->getCrawler()->filter('#new-token code')->text();
        $client->request('GET', '/settings');

        // Assert
        self::assertStringStartsWith('mm_', $plain);
        self::assertNotNull($this->tokens()->findUserByToken($plain));
        self::assertSelectorNotExists('#new-token');
        self::assertSelectorTextContains('#tokens', 'My app');
    }

    public function testARevokedTokenNoLongerOpensTheAccount(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());
        $plain = $this->tokens()->create($this->user, 'To revoke');
        $form = $client->request('GET', '/settings')->selectButton('Revoke')->form();

        // Act
        $client->submit($form);

        // Assert
        self::assertResponseRedirects('/settings');
        self::assertNull($this->tokens()->findUserByToken($plain));
    }

    public function testTheLastConnectionCannotBeRemoved(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser()->linkedTo('local', 'ada@example.org')->linkedTo('github', '42'));

        // Act
        $client->submit($client->request('GET', '/settings')->filter('#connections form')->first()->form());
        $client->submit($client->request('GET', '/settings')->filter('#connections form')->first()->form());

        // Assert
        self::assertResponseRedirects('/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'last connection');
        self::assertCount(1, $client->getCrawler()->filter('#connections li'));
    }
}
