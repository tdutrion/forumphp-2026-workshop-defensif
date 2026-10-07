<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Repository\FilmRepository;
use App\Tests\Builder\FilmBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FilmRepositoryDeprecationTest extends KernelTestCase
{
    use StoresEntities;

    public function testTheOldMethodStillWorksForTheCallersOutsideTheApplicationButSaysItIsDeprecated(): void
    {
        // Arrange
        self::bootKernel();
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->titled('Cars')->build());
        $films = self::getContainer()->get(FilmRepository::class);
        $this->expectUserDeprecationMessageMatches('/^Method App\\\\Catalog\\\\Repository\\\\FilmRepository::findBySlug\(\) is deprecated since exercise 13, Use findFilm\(\) or getFilm\(\)/');

        // Act
        $film = $films->findBySlug('cars');

        // Assert
        self::assertSame('Cars', $film['title'] ?? null);
    }
}
