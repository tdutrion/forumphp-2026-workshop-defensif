<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Command;

use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use App\Tests\StoresEntities;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShiftDatesCommandTest extends KernelTestCase
{
    use StoresEntities;

    public function testShiftsTheCatalogSoThatItStartsToday(): void
    {
        // Arrange
        $kernel = self::bootKernel();
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $city = CityBuilder::aCity()->build();
        $cinema = CinemaBuilder::aCinema()->in($city)->build();
        $film = FilmBuilder::aFilm()->build();
        $showtime = ShowtimeBuilder::aShowtime()->of($film)->at($cinema)->startingAt($today->modify('-3 days')->format('Y-m-d').' 14:00:00')->build();
        $this->store($city, $cinema, $film, $showtime);
        $tester = new CommandTester((new Application($kernel))->find('catalog:shift-dates'));

        // Act
        $tester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('3 day(s)', $tester->getDisplay());
        $row = self::getContainer()->get(Connection::class)->fetchAssociative('SELECT local_date, starts_at FROM showtime');
        self::assertSame($today->format('Y-m-d'), $row['local_date']);
        self::assertSame($showtime->startsAt->modify('+3 days')->format('Y-m-d H:i:s'), $row['starts_at']);
    }
}
