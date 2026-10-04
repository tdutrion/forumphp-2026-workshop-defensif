<?php

namespace App\Tests\Integration\Planner;

use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\Entity\Cinema;
use App\Planner\PlannerService;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
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
            $showtime->withId('V1S2')->of($films['f2'])->startingAt(self::DAY.' 16:30:00')->inVersion('vost')->build(),
            $showtime->withId('V1S3')->of($films['f3'])->startingAt(self::DAY.' 16:40:00')->build(),
            $showtime->withId('V1S4')->of($films['f4'])->startingAt(self::DAY.' 19:00:00')->build(),
            ShowtimeBuilder::aShowtime()->withId('V9S1')->of($films['f5'])->at($vaise)->startingAt(self::DAY.' 15:00:00')->build(),
        );

        return $user->getUserIdentifier();
    }

    private function criteria(array $overrides = []): array
    {
        return $overrides + [
            'date' => self::DAY,
            'latitude' => 47.318031,
            'longitude' => 5.029935,
            'radius' => 10,
            'films' => 2,
            'version' => null,
            'acceptAds' => false,
        ];
    }

    private function filmSets(array $result): array
    {
        return array_column($result['programmes'], 'filmSlugs');
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
        $result = $this->planner()->plan($this->criteria(), $userId);

        // Assert
        self::assertNull($result['reason']);
        self::assertSame([['f3', 'f4'], ['f1', 'f2'], ['f2', 'f4']], $this->filmSets($result));
        $first = $result['programmes'][0];
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
        $result = $this->planner()->plan($this->criteria(['films' => 1]), $userId);

        // Assert
        $times = [];
        foreach ($result['programmes'] as $programme) {
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
        $result = $this->planner()->plan($this->criteria(), $userId);

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
        $result = $this->planner()->plan($this->criteria(), $userId);

        // Assert
        self::assertNotContains('f4', array_merge(...$this->filmSets($result)));
    }

    public function testKeepsOnlyCinemasWithinTheRadiusAndTheChosenVersion(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $within50Km = $this->planner()->plan($this->criteria(['films' => 1, 'radius' => 50]), $userId);
        $dubbedOnly = $this->planner()->plan($this->criteria(['version' => 'vf']), $userId);

        // Assert
        self::assertNotContains(['f5'], $this->filmSets($within50Km), 'Lyon is more than 150 km from Dijon');
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
        $result = $this->planner()->plan($this->criteria(['films' => 1]), $userId);

        // Assert
        self::assertNotContains(['f6'], $this->filmSets($result));
    }

    public function testExplainsWhyThereAreFewerThanThreeProgrammes(): void
    {
        // Arrange
        self::bootKernel();
        $userId = $this->dijonCatalog();

        // Act
        $threeFilms = $this->planner()->plan($this->criteria(['films' => 3]), $userId);
        $fourFilms = $this->planner()->plan($this->criteria(['films' => 4]), $userId);
        $anotherDay = $this->planner()->plan($this->criteria(['date' => '2030-01-11']), $userId);

        // Assert
        self::assertSame('not_enough_programmes', $threeFilms['reason']);
        self::assertSame([['f1', 'f2', 'f4'], ['f1', 'f3', 'f4']], $this->filmSets($threeFilms));
        self::assertSame('fewer_films', $fourFilms['reason'], 'no 4-film marathon: 3-film programmes are offered');
        self::assertSame(3, $fourFilms['films']);
        self::assertSame([['f1', 'f2', 'f4'], ['f1', 'f3', 'f4']], $this->filmSets($fourFilms));
        self::assertFalse($anotherDay, 'no showtime at all on that day');
    }

    public function testFallsBackToTwoFilmsAtLeast(): void
    {
        // Arrange: only f4 remains in Dijon, so not even two films can chain.
        self::bootKernel();
        $userId = $this->dijonCatalog();
        self::getContainer()->get(SeenFilmService::class)->markAllSeen($userId, ['f1', 'f2', 'f3']);

        // Act
        $result = $this->planner()->plan($this->criteria(['films' => 3]), $userId);

        // Assert
        self::assertSame('no_programme', $result['reason']);
        self::assertSame([], $result['programmes']);
    }
}
