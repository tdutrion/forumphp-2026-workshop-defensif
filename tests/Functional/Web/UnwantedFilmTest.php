<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UnwantedFilmTest extends WebTestCase
{
    use StoresEntities;

    private const array SEARCH = ['plan' => ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2]];

    /**
     * Pathé Dijon with four films of 100 minutes on January 10, 2030, and a signed-in user.
     */
    private function signedInWithDijonCatalog(): KernelBrowser
    {
        $client = self::createClient();
        $dijon = CityBuilder::aCity()->build();
        $cinema = CinemaBuilder::aCinema()->withSlug('cinema-pathe-dijon')->in($dijon)->at(47.318031, 5.029935)->build();
        $entities = [$dijon, $cinema];
        foreach (['14:00' => 'f1', '16:30' => 'f2', '16:40' => 'f3', '19:00' => 'f4'] as $time => $slug) {
            $film = FilmBuilder::aFilm()->withSlug($slug)->titled('Film '.$slug)->lasting(100)->build();
            $entities[] = $film;
            $entities[] = ShowtimeBuilder::aShowtime()->of($film)->at($cinema)->startingAt('2030-01-10 '.$time.':00')->build();
        }
        $user = UserBuilder::aUser()->build();
        $this->store($user, ...$entities);
        $client->loginUser($user);

        return $client;
    }

    private function plannedFilms(KernelBrowser $client): array
    {
        return array_merge(...$client->request('GET', '/', self::SEARCH)->filter('.programme')->each(
            static fn ($programme) => $programme->filter('[data-film-slug]')->each(static fn ($showtime) => $showtime->attr('data-film-slug')),
        ));
    }

    public function testAFilmIDoNotWantToSeeLeavesThePlansAndComesBackFromTheFilmsNotForMe(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();
        $crawler = $client->request('GET', '/films/f3');

        // Act
        $client->submit($crawler->filter('[data-unwanted-film="f3"] form')->form());
        $plannedWhileUnwanted = $this->plannedFilms($client);
        $list = $client->request('GET', '/not-for-me');
        $listed = $list->filter('#unwanted-films')->text();
        $client->submit($list->filter('#unwanted-films [data-unwanted-film="f3"] form')->form());
        $plannedAfterUndo = $this->plannedFilms($client);

        // Assert
        self::assertNotContains('f3', $plannedWhileUnwanted);
        self::assertStringContainsString('Film f3', $listed);
        self::assertContains('f3', $plannedAfterUndo);
    }
}
