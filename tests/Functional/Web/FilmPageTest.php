<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Catalog\ShowtimeVersion;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\Builder\WorkBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FilmPageTest extends WebTestCase
{
    use StoresEntities;

    private function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $user = UserBuilder::aUser()->build();
        $this->store($user);
        $client->loginUser($user);

        return $client;
    }

    public function testListsTheKnownShowtimesOfTheFilmByCinemaAndDay(): void
    {
        // Arrange: Garance in two cinemas of Dijon, over two days, plus a showtime long gone.
        $client = $this->signedIn();
        $dijon = CityBuilder::aCity()->build();
        $toison = CinemaBuilder::aCinema()->withSlug('cinema-pathe-toison-d-or')->named('Pathé Toison d\'Or')->in($dijon)->build();
        $centre = CinemaBuilder::aCinema()->withSlug('cinema-pathe-dijon')->named('Pathé Dijon')->in($dijon)->build();
        $film = FilmBuilder::aFilm()->withSlug('garance-52451')->titled('Garance')->build();
        $this->store($dijon, $toison, $centre, $film,
            ShowtimeBuilder::aShowtime()->of($film)->at($centre)->startingAt('2030-01-10 14:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($film)->at($centre)->startingAt('2030-01-10 20:15:00')->inVersion(ShowtimeVersion::Vost)->build(),
            ShowtimeBuilder::aShowtime()->of($film)->at($centre)->startingAt('2030-01-11 16:30:00')->build(),
            ShowtimeBuilder::aShowtime()->of($film)->at($toison)->startingAt('2030-01-11 18:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($film)->at($toison)->startingAt('2020-01-11 18:00:00')->build(),
        );

        // Act
        $crawler = $client->request('GET', '/films/garance-52451', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        // Assert
        $cinemas = $crawler->filter('#showtimes details');
        self::assertCount(2, $cinemas);
        self::assertCount(0, $crawler->filter('#showtimes details[open]'), 'closed by default');
        self::assertStringContainsString('Pathé Dijon', $cinemas->eq(0)->filter('summary')->text());
        $days = $cinemas->eq(0)->filter('[data-day]');
        self::assertSame(['2030-01-10', '2030-01-11'], $days->each(static fn ($day) => $day->attr('data-day')));
        self::assertSame(['14:00', '20:15'], $days->eq(0)->filter('a[data-time]')->each(static fn ($time) => $time->attr('data-time')));
        self::assertStringContainsString('jeudi 10 janvier', $days->eq(0)->text());
        self::assertStringContainsString('VOST', $days->eq(0)->text());
        self::assertStringStartsWith('https://s.pathe.fr/', (string) $days->eq(0)->filter('a[data-time]')->attr('href'));
        self::assertSame(['18:00'], $cinemas->eq(1)->filter('a[data-time]')->each(static fn ($time) => $time->attr('data-time')), 'the past showtime is gone');
    }

    public function testSaysWhenNoShowtimeIsKnown(): void
    {
        // Arrange
        $client = $this->signedIn();
        $this->store(FilmBuilder::aFilm()->withSlug('garance-52451')->titled('Garance')->build());

        // Act
        $client->request('GET', '/films/garance-52451', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        // Assert
        self::assertSelectorTextContains('#showtimes', 'Aucune séance');
    }

    public function testLinksTheFilmToItsWorkElsewhere(): void
    {
        // Arrange
        $client = $this->signedIn();
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->titled('Cars')->ofWork(WorkBuilder::aWork()->titled('Cars')->linkedTo('Q182153', 'tt0317219', '920')->build())->build());

        // Act
        $crawler = $client->request('GET', '/films/cars');

        // Assert
        self::assertSame(
            ['https://www.wikidata.org/wiki/Q182153', 'https://www.imdb.com/title/tt0317219/', 'https://www.themoviedb.org/movie/920'],
            $crawler->filter('#external-links a')->each(static fn ($link) => $link->attr('href')),
        );
    }
}
