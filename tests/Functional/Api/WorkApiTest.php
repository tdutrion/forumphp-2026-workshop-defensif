<?php

namespace App\Tests\Functional\Api;

use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\WorkBuilder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkApiTest extends WebTestCase
{
    use DijonCatalogWithToken;

    public function testAWorkListsItsFilmsAndItsIds(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $work = WorkBuilder::aWork()->titled('Cars')->linkedTo('Q182153', 'tt0317219')->build();
        $this->store(FilmBuilder::aFilm()->withSlug('cars')->titled('Cars')->ofWork($work)->build());

        // Act
        $film = $this->api('GET', '/api/films/cars');
        $found = $this->api('GET', '/api/works/'.$film['work']['id']);
        $this->api('GET', '/api/works/not-a-uuid');
        $malformed = $this->client->getResponse()->getStatusCode();
        $this->api('GET', '/api/works/0192a6f0-0000-7000-8000-000000000000');
        $unknown = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertSame('Q182153', $film['work']['wikidataId']);
        self::assertSame([['chain' => 'pathe', 'slug' => 'cars', 'title' => 'Cars']], $found['films']);
        self::assertSame('tt0317219', $found['imdbId']);
        self::assertSame([404, 404], [$malformed, $unknown]);
    }
}
