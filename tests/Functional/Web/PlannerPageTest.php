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

final class PlannerPageTest extends WebTestCase
{
    use StoresEntities;

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

    private function search(array $criteria): array
    {
        return ['plan' => $criteria + ['date' => '2030-01-10', 'radius' => 10, 'films' => 2]];
    }

    public function testAnonymousVisitorsAreSentToTheSignInPage(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/');

        // Assert
        self::assertResponseRedirects('/login');
    }

    public function testPlansAroundACityWithTheTimesOfTheCinema(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $crawler = $client->request('GET', '/', $this->search(['city' => 'dijon']));

        // Assert
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('.programme'));
        self::assertSelectorTextContains('.programme', 'Film f3');
        self::assertSelectorTextContains('.programme', '16:40');
        self::assertSelectorTextContains('.programme', '20 min break', 'f3 ends at 18:40, f4 starts at 19:00');
        self::assertSelectorTextContains('.programme', 'Total break: 20 min');
    }

    public function testListsTheProposedFilmsAboveProgrammesThatOnlyOfferBooking(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $crawler = $client->request('GET', '/', $this->search(['city' => 'dijon']));

        // Assert
        $listed = $crawler->filter('#proposed-films [data-proposed-film]')->each(static fn ($film) => $film->attr('data-proposed-film'));
        sort($listed);
        self::assertSame(['f1', 'f2', 'f3', 'f4'], $listed, 'each film once, whatever the number of programmes');
        self::assertCount(1, $crawler->filter('#proposed-films details[open]'), 'open by default, can be collapsed');
        self::assertSelectorTextContains('#proposed-films summary', 'Films in these programmes (4)');
        self::assertCount(1, $crawler->filter('#proposed-films [data-proposed-film="f3"] [data-seen-film] form'));
        self::assertCount(1, $crawler->filter('#proposed-films [data-proposed-film="f3"] [data-unwanted-film] form'));
        self::assertCount(0, $crawler->filter('.programme form'), 'a programme only offers booking');
        self::assertSame($crawler->filter('.programme [data-film-slug]')->count(), $crawler->filter('.programme a[data-primary]')->count());
    }

    public function testMarkingAProposedFilmComesBackToTheUpdatedSearch(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();
        $crawler = $client->request('GET', '/', $this->search(['city' => 'dijon']));
        $seen = $crawler->filter('#proposed-films [data-proposed-film="f3"] [data-seen-film] form')->form();
        $notForMe = $crawler->filter('#proposed-films [data-proposed-film="f4"] [data-unwanted-film] form')->form();

        // Act: a plain form post, then a Turbo one: both get a redirect to the same search.
        $client->submit($seen);
        $afterSeen = $client->getResponse();
        $client->submit($notForMe, [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html']);
        $afterNotForMe = $client->getResponse();
        $updated = $client->followRedirect();

        // Assert
        self::assertSame(303, $afterSeen->getStatusCode());
        self::assertSame(303, $afterNotForMe->getStatusCode());
        self::assertStringStartsWith('/?plan', (string) $afterNotForMe->headers->get('Location'));
        $listed = $updated->filter('#proposed-films [data-proposed-film]')->each(static fn ($film) => $film->attr('data-proposed-film'));
        self::assertNotContains('f3', $listed);
        self::assertNotContains('f4', $listed);
    }

    public function testTheSearchCanBeLimitedToATimeRange(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $crawler = $client->request('GET', '/', $this->search(['city' => 'dijon', 'from' => '16:00', 'until' => '21:00']));

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input#plan_from[type=time]');
        self::assertSelectorExists('input#plan_until[type=time]');
        $films = $crawler->filter('.programme [data-film-slug]')->each(static fn ($showtime) => $showtime->attr('data-film-slug'));
        self::assertNotEmpty($films);
        self::assertNotContains('f1', $films, 'f1 starts at 14:00');
    }

    public function testPlansAroundTheBrowserPosition(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $crawler = $client->request('GET', '/', $this->search(['position' => '{"lat": 47.32, "lng": 5.03}']));

        // Assert
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('.programme'));
    }

    public function testExplainsWhyNothingCanBePlanned(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $crawler = $client->request('GET', '/', $this->search(['city' => 'dijon', 'films' => 5]));
        $tooManyFilms = $crawler->filter('.plan-message')->text();
        $fallbackProgrammes = $crawler->filter('.programme')->count();
        $client->request('GET', '/', $this->search(['position' => 'nonsense']));
        $unknownPlace = $client->getCrawler()->filter('.plan-message')->text();

        // Assert
        self::assertStringContainsString('No marathon of 5 films is possible: here are programmes of 3 films.', $tooManyFilms);
        self::assertSame(2, $fallbackProgrammes);
        self::assertStringContainsString('Unknown place', $unknownPlace);
    }

    public function testDatesFollowTheLanguageOfTheBrowser(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $english = $client->request('GET', '/')->filter('#plan_date option[value="2030-01-10"]')->text();
        $client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr-FR,fr;q=0.9');
        $french = $client->request('GET', '/')->filter('#plan_date option[value="2030-01-10"]')->text();

        // Assert
        self::assertSame('Thursday, January 10, 2030', $english);
        self::assertSame('jeudi 10 janvier 2030', $french);
    }

    public function testTheFormOpensOnTomorrowOnceTodaysLastShowtimeHasPassed(): void
    {
        // Arrange: today's only showtime can no longer be booked; tomorrow has one.
        $client = self::createClient();
        $paris = new \DateTimeZone('Europe/Paris');
        $today = new \DateTimeImmutable('today', $paris);
        $city = CityBuilder::aCity()->build();
        $cinema = CinemaBuilder::aCinema()->in($city)->build();
        $film = FilmBuilder::aFilm()->lasting(100)->build();
        $user = UserBuilder::aUser()->build();
        $this->store($city, $cinema, $film, $user,
            ShowtimeBuilder::aShowtime()->withId('V1S1')->of($film)->at($cinema)->startingAt($today->format('Y-m-d').' 00:05:00')
                ->bookableUntil((new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'))->build(),
            ShowtimeBuilder::aShowtime()->withId('V1S2')->of($film)->at($cinema)->startingAt($today->modify('+1 day')->format('Y-m-d').' 20:00:00')->build(),
        );
        $client->loginUser($user);

        // Act
        $crawler = $client->request('GET', '/');

        // Assert
        $dates = $crawler->filter('#plan_date option')->each(static fn ($option) => $option->attr('value'));
        self::assertSame([$today->modify('+1 day')->format('Y-m-d')], $dates);
    }

    public function testTheTravelModeIsAskedWithPublicTransportByDefault(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $crawler = $client->request('GET', '/');

        // Assert
        self::assertSelectorTextContains('label[for=plan_travelMode]', 'Getting around');
        self::assertSame('transit', $crawler->filter('#plan_travelMode option[selected]')->attr('value'));
        self::assertSame(['walking', 'cycling', 'transit', 'car'], $crawler->filter('#plan_travelMode option')->each(static fn ($o) => $o->attr('value')));
    }

    public function testATimeRangeThatEndsBeforeItStartsIsInvalid(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $client->request('GET', '/', $this->search(['city' => 'dijon', 'from' => '20:00', 'until' => '01:00']));

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name=plan]', 'The end of the time range must be after its start.');
        self::assertSelectorNotExists('.programme');
    }

    public function testInvalidCriteriaAreExplained(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();

        // Act
        $client->request('GET', '/', $this->search(['radius' => 80]));

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name=plan]', 'Choose a city or use your position.');
        self::assertSelectorTextContains('form[name=plan]', 'The radius must be between 1 and 50 km.');
    }

    public function testThePageIsInFrenchForAFrenchBrowser(): void
    {
        // Arrange
        $client = $this->signedInWithDijonCatalog();
        $client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr-FR,fr;q=0.9,en;q=0.5');

        // Act
        $client->request('GET', '/', $this->search(['city' => 'dijon', 'radius' => 80]));

        // Assert
        self::assertSelectorTextContains('html', 'Planifier un marathon');
        self::assertSelectorTextContains('label[for=plan_acceptAds]', "J'accepte d'arriver pendant les pubs (15 minutes)");
        self::assertSelectorTextContains('form[name=plan]', 'Le rayon doit être compris entre 1 et 50 km.');
        self::assertSelectorTextContains('header', 'Se déconnecter');
        self::assertSelectorTextContains('#plan_travelMode', 'En transports en commun');
    }
}
