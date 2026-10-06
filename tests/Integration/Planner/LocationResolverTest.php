<?php

namespace App\Tests\Integration\Planner;

use App\Planner\LocationResolver;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocationResolverTest extends KernelTestCase
{
    use StoresEntities;

    public function testACityCentreIsTheBarycentreOfItsCinemas(): void
    {
        // Arrange: Pathé gives Dijon a position 45 km away from the city; its cinemas are reliable.
        self::bootKernel();
        $dijon = CityBuilder::aCity()->build();
        $this->store(
            $dijon,
            CinemaBuilder::aCinema()->withSlug('cinema-pathe-dijon')->in($dijon)->at(47.318031, 5.029935)->build(),
            CinemaBuilder::aCinema()->withSlug('cinema-cine-cap-vert')->in($dijon)->at(47.312465, 5.091471)->build(),
            CityBuilder::aCity()->withSlug('nowhere')->named('Nowhere')->build(),
        );
        $resolver = self::getContainer()->get(LocationResolver::class);

        // Act
        $centre = $resolver->fromCity('dijon');
        $withoutCinema = $resolver->fromCity('nowhere');
        $unknown = $resolver->fromCity('unknown-city');

        // Assert
        self::assertEqualsWithDelta(47.315248, $centre['latitude'], 0.00001);
        self::assertEqualsWithDelta(5.060703, $centre['longitude'], 0.00001);
        self::assertFalse($withoutCinema);
        self::assertFalse($unknown);
    }

    public function testABrowserPositionMustBeRealCoordinates(): void
    {
        // Arrange
        self::bootKernel();
        $resolver = self::getContainer()->get(LocationResolver::class);
        $invalid = ['not json', '{"lat": 47.32}', '{"lat": "abc", "lng": 5}', '{"lat": 123, "lng": 5}', '[]'];

        // Act
        $valid = $resolver->fromPosition('{"lat": 47.32, "lng": 5.04}');
        $rejected = array_map($resolver->fromPosition(...), $invalid);

        // Assert
        self::assertSame(['latitude' => 47.32, 'longitude' => 5.04], $valid);
        self::assertSame([false, false, false, false, false], $rejected);
    }
}
