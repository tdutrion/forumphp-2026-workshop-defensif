<?php

namespace App\Tests\Functional\Web;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeenFilmTest extends WebTestCase
{
    use StoresEntities;

    private User $user;

    private function signedInWithDigger(): KernelBrowser
    {
        $client = self::createClient();
        $this->user = UserBuilder::aUser()->build();
        $this->store($this->user, FilmBuilder::aFilm()->withSlug('digger-51293')->titled('Digger')->lasting(129)->build());
        $client->loginUser($this->user);

        return $client;
    }

    private function seenFilms(): array
    {
        return self::getContainer()->get(SeenFilmService::class)->getSeenFilmSlugs($this->user->getUserIdentifier());
    }

    public function testMarksAndUnmarksAFilmFromItsPage(): void
    {
        // Arrange
        $client = $this->signedInWithDigger();
        $crawler = $client->request('GET', '/films/digger-51293');

        // Act
        $client->submit($crawler->filter('[data-seen-film="digger-51293"] form')->form());
        $afterMarking = $this->seenFilms();
        $client->submit($client->followRedirect()->filter('[data-seen-film="digger-51293"] form')->form());

        // Assert
        self::assertSame(['digger-51293'], $afterMarking);
        self::assertSame([], $this->seenFilms());
    }

    public function testTurboGetsAStreamThatReplacesEveryButtonOfTheFilm(): void
    {
        // Arrange
        $client = $this->signedInWithDigger();
        $token = $client->request('GET', '/films/digger-51293')->filter('[data-seen-film="digger-51293"] input[name=_token]')->attr('value');

        // Act
        $client->request('POST', '/films/digger-51293/seen', ['_token' => $token], [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html']);

        // Assert
        self::assertResponseHeaderSame('content-type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        self::assertStringContainsString('targets="[data-seen-film=&quot;digger-51293&quot;]"', (string) $client->getResponse()->getContent());
    }

    public function testRefusesAFormWithoutItsCsrfToken(): void
    {
        // Arrange
        $client = $this->signedInWithDigger();

        // Act
        $client->request('POST', '/films/digger-51293/seen');

        // Assert
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->seenFilms());
    }

    public function testAProgrammeIsMarkedAsSeenInOneGoEvenWhenSubmittedTwice(): void
    {
        // Arrange
        $client = $this->signedInWithDigger();
        $this->store(FilmBuilder::aFilm()->withSlug('verity-50815')->titled('Verity')->lasting(117)->build());
        $token = $client->request('GET', '/films/digger-51293')->filter('[data-seen-film="digger-51293"] input[name=_token]')->attr('value');
        $programme = ['_token' => $token, 'films' => ['digger-51293', 'verity-50815']];

        // Act
        $client->request('POST', '/programmes/seen', $programme);
        $client->request('POST', '/programmes/seen', $programme);

        // Assert
        self::assertResponseRedirects('/profile');
        $seen = $this->seenFilms();
        sort($seen);
        self::assertSame(['digger-51293', 'verity-50815'], $seen);
    }
}
