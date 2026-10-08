<?php

namespace App\Tests\Integration\Planner;

use App\Account\ExcludedCinemaService;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\Entity\Cinema;
use App\Catalog\Entity\Film;
use App\Catalog\ShowtimeVersion;
use App\Planner\PlanFailure;
use App\Planner\PlannerService;
use App\Planner\PlanRequest;
use App\Planner\PlanResult;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PlannerServiceTest extends KernelTestCase
{
    use StoresEntities;

    private const DAY = '2030-01-10';

    private Cinema $dijon;

    /**
     * Pathé Dijon, 5 films of 100 minutes (each showtime lasts 2 hours with the ads), and Pathé Vaise in Lyon,
     * 150 km away. Returns the identifier of the user who plans; keeps Pathé Dijon in $this->dijon.
     */
    private function dijonCatalog(): string
    {
        $this->dijon = $dijon = CinemaBuilder::aCinema()->withSlug('cinema-pathe-dijon')->in(CityBuilder::aCity()->build())->at(47.318031, 5.029935)->build();
        $vaise = CinemaBuilder::aCinema()->withSlug('cinema-pathe-vaise')->in(CityBuilder::aCity()->withSlug('lyon')->named('Lyon')->build())->at(45.7746, 4.8048)->build();
        $films = [];
        foreach (['f1', 'f2', 'f3', 'f4', 'f5'] as $slug) {
            $films[$slug] = FilmBuilder::aFilm()->withSlug($slug)->lasting(100)->build();
        }
        $showtime = ShowtimeBuilder::aShowtime()->at($dijon);
        $user = UserBuilder::aUser()->build();
        $this->store(
            $dijon->getCity(), $dijon, $vaise->getCity(), $vaise, $user, ...array_values($films),
        );
        $this->store(
            $showtime->withId('V1S1')->of($films['f1'])->startingAt(self::DAY.' 14:00:00')->build(),
            $showtime->withId('V1S2')->of($films['f2'])->startingAt(self::DAY.' 16:30:00')->inVersion(ShowtimeVersion::Vost)->build(),
            $showtime->withId('V1S3')->of($films['f3'])->startingAt(self::DAY.' 16:40:00')->build(),
            $showtime->withId('V1S4')->of($films['f4'])->startingAt(self::DAY.' 19:00:00')->build(),
            ShowtimeBuilder::aShowtime()->withId('V9S1')->of($films['f5'])->at($vaise)->startingAt(self::DAY.' 15:00:00')->build(),
        );

        return $user->getUserIdentifier();
    }

    /**
     * A search around Pathé Dijon on self::DAY, for 2 films by default.
     */
    private function request(array $overrides = []): PlanRequest
    {
        return new PlanRequest(...$overrides + ['date' => self::DAY, 'position' => '{"lat": 47.318031, "lng": 5.029935}']);
    }

    private function filmSets(PlanResult $result): array
    {
        return array_column($result->programmes->toArray(), 'filmSlugs');
    }

    private function planner(): PlannerService
    {
        return self::getContainer()->get(PlannerService::class);
    }

    public function testPlansTheThreeBestDistinctProgrammesInLocalTime(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $result = $this->planner()->plan($this->request(), $userId);

        // Assert
        self::assertNull($result->reason());
        self::assertSame([['f3', 'f4'], ['f1', 'f2'], ['f2', 'f4']], $this->filmSets($result));
        $first = $result->programmes->toArray()[0];
        self::assertSame(10, $first['wait']);
        self::assertSame('16:40', $first['showtimes'][0]['startTime'], 'stored as 15:40 UTC, shown in Dijon time');
        self::assertSame('18:40', $first['showtimes'][0]['endTime']);
        self::assertSame('cinema-pathe-dijon', $first['showtimes'][0]['cinemaSlug']);
    }

    public function testShowsTheTimesOfEachCinemaInItsOwnTimeZone(): void
    {
        // Arrange: a cinema of another chain, at the same place but on London time.
        self::bootKernel();
        $userId = $this->dijonCatalog();
        $elsewhere = CinemaBuilder::aCinema()->withSlug('cinema-elsewhere')->in(CityBuilder::aCity()->withSlug('elsewhere')->named('Elsewhere')->build())->inTimezone('Europe/London')->build();
        $film = FilmBuilder::aFilm()->withSlug('f7')->lasting(100)->build();
        $this->store($elsewhere->getCity(), $elsewhere, $film, ShowtimeBuilder::aShowtime()->of($film)->at($elsewhere)->startingAt(self::DAY.' 10:00:00')->build());

        // Act
        $result = $this->planner()->plan($this->request(['films' => 1]), $userId);

        // Assert
        $times = [];
        foreach ($result->programmes as $programme) {
            $times[$programme['filmSlugs'][0]] = $programme['showtimes'][0]['startTime'];
        }
        self::assertSame('10:00', $times['f7'] ?? null);
    }

    public function testExcludesFilmsAlreadySeen(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();
        self::getContainer()->get(SeenFilmService::class)->markSeen($userId, 'f3');

        // Act
        $result = $this->planner()->plan($this->request(), $userId);

        // Assert
        self::assertNotContains('f3', array_merge(...$this->filmSets($result)));
    }

    public function testExcludesFilmsTheUserDoesNotWantToSee(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();
        self::getContainer()->get(UnwantedFilmService::class)->markUnwanted($userId, 'f4');

        // Act
        $result = $this->planner()->plan($this->request(), $userId);

        // Assert
        self::assertNotContains('f4', array_merge(...$this->filmSets($result)));
    }

    public function testTheOriginalVersionAlsoKeepsFilmsMadeInTheLanguageOfTheCinema(): void
    {
        // Arrange: in Dijon (a French cinema), f3 is a French film shown in VF, f1 an American film in VF.
        self::bootKernel();
        $userId = $this->dijonCatalog();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->find(Film::class, 'f3')->setOriginalLanguage('fr');
        $em->find(Film::class, 'f1')->setOriginalLanguage('en');
        $em->flush();

        // Act
        $result = $this->planner()->plan($this->request(['films' => 1, 'version' => ShowtimeVersion::Vost]), $userId);

        // Assert
        $films = array_merge(...$this->filmSets($result));
        self::assertContains('f2', $films, 'shown in VOST');
        self::assertContains('f3', $films, 'French film: its VF is its original version');
        self::assertNotContains('f1', $films, 'dubbed into French');
    }

    public function testTheLanguageOfTheCinemaDecidesWhatIsAnOriginalVersion(): void
    {
        // Arrange: an English-speaking cinema (a Cineworld-like chain) at the same place.
        self::bootKernel();
        $userId = $this->dijonCatalog();
        $london = CinemaBuilder::aCinema()->withSlug('cinema-in-english')->in(CityBuilder::aCity()->withSlug('elsewhere')->named('Elsewhere')->build())->inTimezone('Europe/London')->speaking('en')->build();
        $english = FilmBuilder::aFilm()->withSlug('english-film')->lasting(100)->inOriginalLanguage('en')->build();
        $french = FilmBuilder::aFilm()->withSlug('french-film')->lasting(100)->inOriginalLanguage('fr')->build();
        $this->store($london->getCity(), $london, $english, $french,
            ShowtimeBuilder::aShowtime()->of($english)->at($london)->startingAt(self::DAY.' 12:00:00')->build(),
            ShowtimeBuilder::aShowtime()->of($french)->at($london)->startingAt(self::DAY.' 12:00:00')->build(),
        );

        // Act
        $result = $this->planner()->plan($this->request(['films' => 1, 'version' => ShowtimeVersion::Vo]), $userId);

        // Assert
        $films = array_merge(...$this->filmSets($result));
        self::assertContains('english-film', $films, 'English film in an English cinema: original version');
        self::assertNotContains('french-film', $films, 'not in its original language there');
    }

    public function testKeepsOnlyShowtimesWithinTheTimeRange(): void
    {
        // Arrange: f1 14:00–16:00, f2 16:30–18:30, f3 16:40–18:40, f4 19:00–21:00 (local time).
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $afternoon = $this->planner()->plan($this->request(['from' => '16:00']), $userId);
        $beforeDinner = $this->planner()->plan($this->request(['until' => '18:45']), $userId);

        // Assert
        self::assertNotContains('f1', array_merge(...$this->filmSets($afternoon)), 'starts before 16:00');
        self::assertFalse($afternoon->programmes->isEmpty());
        self::assertNotContains('f4', array_merge(...$this->filmSets($beforeDinner)), 'ends after 18:45');
        self::assertFalse($beforeDinner->programmes->isEmpty());
    }

    public function testTheTimeRangeIsInLocalTimeOnTheDayTheClocksChange(): void
    {
        // Arrange: on 2030-10-27, Dijon goes back from UTC+2 to UTC+1 at 3:00; a showtime 20:00–22:00 local time.
        self::bootKernel();
        $userId = $this->dijonCatalog();
        $film = FilmBuilder::aFilm()->withSlug('f8')->lasting(100)->build();
        $this->store($film, ShowtimeBuilder::aShowtime()->withId('V1S8')->of($film)->at($this->dijon)->startingAt('2030-10-27 20:00:00')->build());

        // Act
        $result = $this->planner()->plan($this->request(['date' => '2030-10-27', 'films' => 1, 'from' => '20:00', 'until' => '22:00']), $userId);

        // Assert
        self::assertTrue($result->isSuccess());
        self::assertSame([['f8']], $this->filmSets($result));
    }

    public function testNeverUsesACinemaTheUserExcluded(): void
    {
        // Arrange: a second cinema next to Pathé Dijon, which the user excludes.
        self::bootKernel();
        $userId = $this->dijonCatalog();
        $other = CinemaBuilder::aCinema()->withSlug('cinema-cine-cap-vert')->in($this->dijon->getCity())->at(47.312465, 5.091471)->build();
        $film = FilmBuilder::aFilm()->withSlug('f8')->lasting(100)->build();
        $this->store($other, $film, ShowtimeBuilder::aShowtime()->of($film)->at($other)->startingAt(self::DAY.' 20:00:00')->build());
        self::getContainer()->get(ExcludedCinemaService::class)->exclude($userId, 'cinema-pathe-dijon');

        // Act
        $result = $this->planner()->plan($this->request(['films' => 1]), $userId);

        // Assert
        self::assertSame([['f8']], $this->filmSets($result), 'only the other cinema is used');
    }

    public function testKeepsOnlyCinemasWithinTheRadiusAndTheChosenVersion(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $around = $this->planner()->plan($this->request(['films' => 1]), $userId);
        $dubbedOnly = $this->planner()->plan($this->request(['version' => ShowtimeVersion::Vf]), $userId);

        // Assert
        self::assertNotContains(['f5'], $this->filmSets($around), 'Lyon is more than 150 km from Dijon');
        self::assertNotContains('f2', array_merge(...$this->filmSets($dubbedOnly)), 'f2 only plays in original version');
    }

    public function testNeverProposesAShowtimeWhoseBookingIsClosed(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();
        $film = FilmBuilder::aFilm()->withSlug('f6')->lasting(100)->build();
        $this->store($film, ShowtimeBuilder::aShowtime()->of($film)->at($this->dijon)
            ->startingAt(self::DAY.' 21:30:00')->bookableUntil('2020-01-01 00:00:00')->build());

        // Act
        $result = $this->planner()->plan($this->request(['films' => 1]), $userId);

        // Assert
        self::assertNotContains(['f6'], $this->filmSets($result));
    }

    public function testExplainsWhyThereAreFewerThanThreeProgrammes(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $threeFilms = $this->planner()->plan($this->request(['films' => 3]), $userId);
        $fourFilms = $this->planner()->plan($this->request(['films' => 4]), $userId);
        $anotherDay = $this->planner()->plan($this->request(['date' => '2030-01-11']), $userId);

        // Assert
        self::assertSame('not_enough_programmes', $threeFilms->reason());
        self::assertSame([['f1', 'f2', 'f4'], ['f1', 'f3', 'f4']], $this->filmSets($threeFilms));
        self::assertSame('fewer_films', $fourFilms->reason(), 'no 4-film marathon: 3-film programmes are offered');
        self::assertSame(3, $fourFilms->films);
        self::assertSame([['f1', 'f2', 'f4'], ['f1', 'f3', 'f4']], $this->filmSets($fourFilms));
        self::assertSame(PlanFailure::NoShowtime, $anotherDay->failure, 'no showtime at all on that day');
    }

    public function testFallsBackToSingleFilmsWhenNothingChains(): void
    {
        // Arrange: only f4 remains in Dijon, so not even two films can chain.
        self::bootKernel();
        $userId = $this->dijonCatalog();
        foreach (['f1', 'f2', 'f3'] as $slug) {
            self::getContainer()->get(SeenFilmService::class)->markSeen($userId, $slug);
        }

        // Act
        $result = $this->planner()->plan($this->request(['films' => 3]), $userId);

        // Assert
        self::assertSame('fewer_films', $result->reason());
        self::assertSame(1, $result->films);
        self::assertSame([['f4']], $this->filmSets($result));
    }

    public function testAsksForTwoFilmsByDefaultAndAcceptsOne(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();
        // Act
        $byDefault = $this->planner()->plan($this->request(), $userId);
        $single = $this->planner()->plan($this->request(['films' => 1]), $userId);

        // Assert
        self::assertSame(2, $byDefault->films);
        self::assertNull($single->reason());
        self::assertSame(1, $single->films);
    }

    public function testFailsWhenThePlaceIsUnknown(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $result = $this->planner()->plan($this->request(['position' => '{"lat": 0}']), $userId);

        // Assert
        self::assertFalse($result->isSuccess());
        self::assertSame(PlanFailure::UnknownLocation, $result->failure);
        self::assertTrue($result->programmes->isEmpty());
        self::assertSame('unknown_location', $result->reason());
    }

    public function testFailsWithNoProgrammeWhenTheTimeRangeKeepsNothingThatChains(): void
    {
        // Arrange: a window of one minute holds no film at all, yet showtimes exist that day.
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $result = $this->planner()->plan($this->request(['films' => 1, 'from' => '03:00', 'until' => '03:01']), $userId);

        // Assert
        self::assertSame(PlanFailure::NoProgramme, $result->failure);
        self::assertSame(1, $result->films, 'the search went down to single films');
        self::assertNotNull($result->seed);
    }
}
