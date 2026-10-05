<?php

namespace App\Tests\Functional\Web;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FilmCatalogTest extends WebTestCase
{
    use StoresEntities;

    private User $user;

    /**
     * Dijon shows Garance (drama, 3 showtimes) and Digger (action, 1 showtime); Lyon shows Verity
     * (thriller, 2 showtimes); Old Film only had a showtime long gone.
     */
    private function signedInWithCatalog(): KernelBrowser
    {
        $client = self::createClient();
        $this->user = UserBuilder::aUser()->build();
        $dijon = CityBuilder::aCity()->build();
        $lyon = CityBuilder::aCity()->withSlug('lyon')->named('Lyon')->build();
        $inDijon = CinemaBuilder::aCinema()->withSlug('cinema-pathe-dijon')->named('Pathé Dijon')->in($dijon)->build();
        $inLyon = CinemaBuilder::aCinema()->withSlug('cinema-pathe-vaise')->named('Pathé Vaise')->in($lyon)->build();
        $garance = FilmBuilder::aFilm()->withSlug('garance')->titled('Garance')->inGenres('Drame')->build();
        $digger = FilmBuilder::aFilm()->withSlug('digger')->titled('Digger')->inGenres('Action', 'Comédie')->build();
        $verity = FilmBuilder::aFilm()->withSlug('verity')->titled('Verity')->inGenres('Thriller')->build();
        $old = FilmBuilder::aFilm()->withSlug('old-film')->titled('Old Film')->inGenres('Drame')->build();
        $this->store($this->user, $dijon, $lyon, $inDijon, $inLyon, $garance, $digger, $verity, $old,
            ShowtimeBuilder::aShowtime()->of($garance)->at($inDijon)->startingAt('2030-01-10 14:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($garance)->at($inDijon)->startingAt('2030-01-10 18:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($garance)->at($inDijon)->startingAt('2030-01-11 18:00:00')->inVersion('vost')->build(),
            ShowtimeBuilder::aShowtime()->of($digger)->at($inDijon)->startingAt('2030-01-10 20:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($verity)->at($inLyon)->startingAt('2030-01-10 16:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($verity)->at($inLyon)->startingAt('2030-01-10 21:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($old)->at($inDijon)->startingAt('2020-01-10 14:00:00')->build(),
        );
        $client->loginUser($this->user);

        return $client;
    }

    private function titles(KernelBrowser $client, string $url): array
    {
        return $client->request('GET', $url)->filter('#films [data-film]')->each(static fn ($film) => $film->filter('h2')->text());
    }

    public function testListsTheFilmsShowingByTitleFromTheHeader(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();

        // Act
        $link = $client->request('GET', '/')->filter('header a[href="/films"]');
        $titles = $this->titles($client, '/films');

        // Assert
        self::assertCount(1, $link);
        self::assertSame(['Digger', 'Garance', 'Verity'], $titles, 'films that can still be booked, by title');
        self::assertSelectorTextContains('#films [data-film="garance"]', '3');
    }

    public function testFiltersAndSortsFromTheUrl(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();

        // Act
        $byShowtimes = $this->titles($client, '/films?sort=showtimes');
        $inDijon = $this->titles($client, '/films?city=dijon');
        $dramas = $this->titles($client, '/films?genre=Drame');
        $searched = $this->titles($client, '/films?q=ver');
        $inVost = $this->titles($client, '/films?version=vost');

        // Assert
        self::assertSame(['Garance', 'Verity', 'Digger'], $byShowtimes);
        self::assertSame(['Digger', 'Garance'], $inDijon);
        self::assertSame(['Garance'], $dramas);
        self::assertSame(['Verity'], $searched);
        self::assertSame(['Garance'], $inVost);
    }

    public function testHidesTheFilmsAlreadySeenOnDemand(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();
        self::getContainer()->get(SeenFilmService::class)->markSeen($this->user->getUserIdentifier(), 'garance');

        // Act
        $all = $this->titles($client, '/films');
        $notSeen = $this->titles($client, '/films?hide_marked=1');

        // Assert
        self::assertSame(['Digger', 'Garance', 'Verity'], $all);
        self::assertSame(['Digger', 'Verity'], $notSeen);
    }

    public function testAnUnknownSortIsRefused(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();

        // Act
        $client->request('GET', '/films?sort=price');

        // Assert
        self::assertResponseStatusCodeSame(400);
    }
}
