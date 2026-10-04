<?php

namespace App\Tests\Functional\Web;

use App\Account\ApiTokenService;
use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileTest extends WebTestCase
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

    public function testShowsTheSeenFilmsTheConnectionsAndTheProvidersLeftToLink(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());
        $this->store(FilmBuilder::aFilm()->withSlug('digger-51293')->titled('Digger')->lasting(129)->build());
        self::getContainer()->get(SeenFilmService::class)->markSeen($this->user->getUserIdentifier(), 'digger-51293');

        // Act
        $client->request('GET', '/profile');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#seen-films', 'Digger');
        self::assertSelectorTextContains('#connections', 'local');
        self::assertSelectorExists('#connections a[href="/connect/github?link=1"]');
        self::assertSelectorNotExists('#connections a[href="/connect/local?link=1"]');
    }

    public function testANewTokenIsShownOnlyOnce(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser());
        $form = $client->request('GET', '/profile')->selectButton('Create a token')->form(['name' => 'My app']);

        // Act
        $client->submit($form);
        $plain = $client->getCrawler()->filter('#new-token code')->text();
        $client->request('GET', '/profile');

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
        $form = $client->request('GET', '/profile')->selectButton('Revoke')->form();

        // Act
        $client->submit($form);

        // Assert
        self::assertResponseRedirects('/profile');
        self::assertNull($this->tokens()->findUserByToken($plain));
    }

    public function testTheLastConnectionCannotBeRemoved(): void
    {
        // Arrange
        $client = $this->signedInAs(UserBuilder::aUser()->linkedTo('local', 'ada@example.org')->linkedTo('github', '42'));

        // Act
        $client->submit($client->request('GET', '/profile')->filter('#connections form')->first()->form());
        $client->submit($client->request('GET', '/profile')->filter('#connections form')->first()->form());

        // Assert
        self::assertResponseRedirects('/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'last connection');
        self::assertCount(1, $client->getCrawler()->filter('#connections li'));
    }
}
