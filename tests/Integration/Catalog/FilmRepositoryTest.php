<?php

namespace App\Tests\Integration\Catalog;

use App\Catalog\FilmNotFound;
use App\Catalog\FilmSlug;
use App\Catalog\Repository\FilmRepository;
use App\Tests\Builder\FilmBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FilmRepositoryTest extends KernelTestCase
{
    use StoresEntities;

    public function testFindsAFilmOrNothing(): void
    {
        // Arrange
        self::bootKernel();
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->titled('Cars')->build());
        $films = self::getContainer()->get(FilmRepository::class);

        // Act
        $found = $films->findFilm(new FilmSlug('cars'));
        $missing = $films->findFilm(new FilmSlug('unknown-film'));

        // Assert
        self::assertSame('Cars', $found?->getTitle());
        self::assertNull($missing);
    }

    public function testGetsAFilmOrFailsWithTheSlugItWasAskedFor(): void
    {
        // Arrange
        self::bootKernel();
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->titled('Cars')->build());
        $films = self::getContainer()->get(FilmRepository::class);

        // Act
        $found = $films->getFilm(new FilmSlug('cars'));
        try {
            $films->getFilm(new FilmSlug('unknown-film'));
            self::fail('An unknown film must not be returned.');
        } catch (FilmNotFound $e) {
            // Assert
            self::assertSame('Cars', $found->getTitle());
            self::assertSame('unknown-film', $e->slug->value);
        }
    }
}
