<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sdk\Pathe;

use App\Sdk\Pathe\CinemaSlug;
use App\Sdk\Pathe\PatheEndpoints;
use App\Sdk\Pathe\ShowSlug;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PatheEndpointsTest extends TestCase
{
    public function testEveryCallIsResolvedFromTheBase(): void
    {
        // Arrange
        $endpoints = new PatheEndpoints();
        $show = new ShowSlug('digger-51293');
        $cinema = new CinemaSlug('cinema-pathe-dijon');

        // Act
        $urls = [
            $endpoints->cities()->toString(),
            $endpoints->cinemas()->toString(),
            $endpoints->shows()->toString(),
            $endpoints->show($show)->toString(),
            $endpoints->cinemaProgramme($cinema)->toString(),
            $endpoints->showtimes($show, $cinema)->toString(),
        ];

        // Assert
        self::assertSame([
            'https://www.pathe.fr/api/cities?language=fr',
            'https://www.pathe.fr/api/cinemas?language=fr',
            'https://www.pathe.fr/api/shows?language=fr',
            'https://www.pathe.fr/api/show/digger-51293?language=fr',
            'https://www.pathe.fr/api/cinema/cinema-pathe-dijon/shows?language=fr',
            'https://www.pathe.fr/api/show/digger-51293/showtimes/cinema-pathe-dijon?language=fr',
        ], $urls);
    }

    public function testAnotherBaseCanBeGiven(): void
    {
        // Act
        $url = (new PatheEndpoints('https://pathe.example.org/v2/api/'))->cities();

        // Assert
        self::assertSame('https://pathe.example.org/v2/api/cities?language=fr', $url->toString());
    }

    #[TestWith([''])]
    #[TestWith(['http://www.pathe.fr/api/'])]
    #[TestWith(['https://www.pathe.fr/api'])]
    #[TestWith(['/api/'])]
    #[TestWith(['https:///api/'])]
    #[TestWith(['https://exa mple.org/api/'])]
    public function testABaseThatIsNotTheRootOfAnHttpsApiFailsAtConstruction(string $base): void
    {
        // Assert: not on the first call, hours later
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new PatheEndpoints($base);
    }
}
