<?php

namespace App\Tests\Functional\Web;

use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExcludedCinemaTest extends WebTestCase
{
    use StoresEntities;

    private const SEARCH = ['plan' => ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2]];

    /**
     * Pathé Dijon with four films of 100 minutes on January 10, 2030, and a signed-in user.
     */
    private function signedInWithDijonCatalog(): KernelBrowser
    {
        $client = self::createClient();
        $dijon = CityBuilder::aCity()->build();
        $cinema = CinemaBuilder::aCinema()->withSlug('cinema-pathe-dijon')->named('Pathé Dijon')->in($dijon)->at(47.318031, 5.029935)->build();
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

    public function testACinemaExcludedFromASearchComesBackFromMySettings(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();
        $crawler = $client->request('GET', '/', self::SEARCH);
        $listedBefore = $crawler->filter('#nearby-cinemas [data-nearby-cinema]')->each(static fn ($row) => $row->attr('data-nearby-cinema'));

        // Act
        $client->submit($crawler->filter('#nearby-cinemas [data-excluded-cinema="cinema-pathe-dijon"] form')->form());
        $redirect = $client->getResponse();
        $withoutTheCinema = $client->followRedirect()->filter('.programme')->count();
        $settings = $client->request('GET', '/settings');
        $listedInSettings = $settings->filter('#excluded-cinemas')->text();
        $client->submit($settings->filter('#excluded-cinemas [data-excluded-cinema="cinema-pathe-dijon"] form')->form());
        $withTheCinemaAgain = $client->request('GET', '/', self::SEARCH)->filter('.programme')->count();

        // Assert
        self::assertSame(['cinema-pathe-dijon'], $listedBefore);
        self::assertSame(303, $redirect->getStatusCode());
        self::assertSame(0, $withoutTheCinema);
        self::assertStringContainsString('Pathé Dijon', $listedInSettings);
        self::assertSame(3, $withTheCinemaAgain);
    }

    public function testTheNearbyCinemasStayListedToReactivateAnExcludedOne(): void
    {
        // Arrange: the only cinema of the search is excluded, so nothing can be planned.
        $client = $this->signedInWithDijonCatalog();
        $crawler = $client->request('GET', '/', self::SEARCH);
        $client->submit($crawler->filter('#nearby-cinemas [data-excluded-cinema="cinema-pathe-dijon"] form')->form());
        $withoutProgramme = $client->followRedirect();

        // Act
        $client->submit($withoutProgramme->filter('#nearby-cinemas [data-excluded-cinema="cinema-pathe-dijon"] form')->form());
        $reactivated = $client->followRedirect();

        // Assert
        self::assertCount(1, $crawler->filter('#nearby-cinemas details:not([open])'), 'closed by default');
        self::assertSame('Nearby cinemas (1/1)', trim($crawler->filter('#nearby-cinemas summary h2')->text()), 'active / total');
        self::assertSame('Nearby cinemas (0/1)', trim($withoutProgramme->filter('#nearby-cinemas summary h2')->text()), 'one excluded');
        self::assertSame(0, $withoutProgramme->filter('.programme')->count());
        self::assertSame('true', $withoutProgramme->filter('#nearby-cinemas [data-excluded-cinema="cinema-pathe-dijon"] button')->attr('aria-pressed'));
        self::assertSame(3, $reactivated->filter('.programme')->count());
    }
}
