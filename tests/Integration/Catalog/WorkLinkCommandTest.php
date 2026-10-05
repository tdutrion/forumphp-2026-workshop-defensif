<?php

namespace App\Tests\Integration\Catalog;

use App\Catalog\Entity\Film;
use App\Catalog\WorkLinkStatus;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\WikidataApiBuilder;
use App\Tests\Fake\FakeWikidataApi;
use App\Tests\StoresEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class WorkLinkCommandTest extends KernelTestCase
{
    use StoresEntities;

    private function command(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('work:link'));
    }

    public function testLinksTheWorkOfAFilmByHandOrStopsTheAttempts(): void
    {
        // Arrange
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve(WikidataApiBuilder::aWikidataApi()->withFilm('Q182153', 'Cars', 2006, ['John Lasseter'], 'tt0317219'));
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->build(), FilmBuilder::aFilm()->withSlug('unknown-film')->build());

        // Act
        $linked = $this->command()->execute(['film' => 'cars', 'wikidata-id' => 'Q182153']);
        $none = $this->command()->execute(['film' => 'unknown-film', '--none' => true]);
        $missing = $this->command()->execute(['film' => 'no-such-film', 'wikidata-id' => 'Q1']);

        // Assert
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        self::assertSame([0, 0, 1], [$linked, $none, $missing]);
        self::assertSame('tt0317219', $em->find(Film::class, 'cars')->getWork()->getImdbId());
        self::assertSame(WorkLinkStatus::NoMatch, $em->find(Film::class, 'unknown-film')->getWork()->getLinkStatus());
    }
}
