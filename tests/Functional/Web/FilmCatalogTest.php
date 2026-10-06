<?php

namespace App\Tests\Functional\Web;

use App\Account\Entity\User;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\Entity\Cinema;
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

    public function testHidesTheFilmsSeenAndThoseNotForMeSeparately(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();
        $userId = $this->user->getUserIdentifier();
        self::getContainer()->get(SeenFilmService::class)->markSeen($userId, 'garance');
        self::getContainer()->get(UnwantedFilmService::class)->markUnwanted($userId, 'digger');

        // Act
        $all = $this->titles($client, '/films');
        $notSeen = $this->titles($client, '/films?hide_seen=1');
        $forMe = $this->titles($client, '/films?hide_unwanted=1');
        $neither = $this->titles($client, '/films?hide_seen=1&hide_unwanted=1');

        // Assert
        self::assertSame(['Digger', 'Garance', 'Verity'], $all);
        self::assertSame(['Digger', 'Verity'], $notSeen);
        self::assertSame(['Garance', 'Verity'], $forMe);
        self::assertSame(['Verity'], $neither);
    }

    public function testHidingTheMarkedFilmsIsTwoSwitches(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();

        // Act
        $client->request('GET', '/films');

        // Assert
        self::assertSelectorExists('input[name=hide_seen][type=checkbox][role=switch].switch');
        self::assertSelectorExists('input[name=hide_unwanted][type=checkbox][role=switch].switch');
    }

    public function testFiltersByCinemaWeekFromWednesdayToTuesday(): void
    {
        // Arrange: the catalog plays in the week of Wednesday January 9, 2030; Later opens the next Wednesday.
        $client = $this->signedInWithCatalog();
        $later = FilmBuilder::aFilm()->withSlug('later')->titled('Later')->build();
        $dijon = self::getContainer()->get('doctrine')->getManager()->find(Cinema::class, 'cinema-pathe-dijon');
        $this->store($later, ShowtimeBuilder::aShowtime()->of($later)->at($dijon)->startingAt('2030-01-16 14:00:00')->build());

        // Act
        $weeks = $client->request('GET', '/films')->filter('select[name=week] option')->each(static fn ($option) => $option->attr('value'));
        $firstWeek = $this->titles($client, '/films?week=2030-01-09');
        $nextWeek = $this->titles($client, '/films?week=2030-01-16');

        // Assert
        self::assertSame(['', '2030-01-09', '2030-01-16'], $weeks);
        self::assertSame(['Digger', 'Garance', 'Verity'], $firstWeek);
        self::assertSame(['Later'], $nextWeek);
    }

    public function testAWeekThatIsNotADateIsRefused(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();

        // Act
        $client->request('GET', '/films?week=next');

        // Assert
        self::assertResponseStatusCodeSame(400);
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

    public function testTheLanguageAndThemeSwitchesStayOnThePageWithItsCriteria(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();
        $crawler = $client->request('GET', '/films?sort=showtimes&city=dijon');

        // Act
        $client->submit($crawler->filter('#language-switch button[value="fr"]')->form());
        $afterLanguage = $client->getResponse()->headers->get('Location');
        $client->submit($client->request('GET', '/films?sort=showtimes&city=dijon')->filter('#theme-switch form')->form());
        $afterTheme = $client->getResponse()->headers->get('Location');

        // Assert (the query string is normalized: its parameters come sorted)
        self::assertSame('/films?city=dijon&sort=showtimes', $afterLanguage);
        self::assertSame('/films?city=dijon&sort=showtimes', $afterTheme);
    }

    public function testMarksAFilmFromThePageAndComesBackToItsCriteria(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();
        $crawler = $client->request('GET', '/films?city=dijon&sort=showtimes');

        // Act
        $client->submit($crawler->filter('#films [data-film="garance"] [data-seen-film] button')->form());
        $redirect = $client->getResponse()->headers->get('Location');
        $client->submit($client->request('GET', '/films?city=dijon')->filter('#films [data-film="digger"] [data-unwanted-film] button')->form());
        $card = $client->followRedirect()->filter('#films [data-film="digger"]');

        // Assert
        self::assertSame('/films?city=dijon&sort=showtimes&page=1', $redirect);
        self::assertContains('garance', self::getContainer()->get(SeenFilmService::class)->getSeenFilmSlugs($this->user->getUserIdentifier()));
        self::assertSame('true', $card->filter('[data-unwanted-film] button')->attr('aria-pressed'), 'the button shows the new state');
    }

    public function testTheEmptyFieldsOfTheFormMeanNoCriterion(): void
    {
        // Arrange
        $client = $this->signedInWithCatalog();

        // Act: what the form sends when only the switches are on.
        $titles = $this->titles($client, '/films?q=&sort=title&genre=&city=&version=&hide_seen=1&hide_unwanted=1');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSame(['Digger', 'Garance', 'Verity'], $titles);
    }
}
