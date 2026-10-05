<?php

namespace App\Tests\Functional;

use App\Tests\Functional\Api\DijonCatalogWithToken;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The website and the API must propose exactly the same programmes.
 */
final class WebApiParityTest extends WebTestCase
{
    use DijonCatalogWithToken;

    public function testWebAndApiProposeTheSameProgrammes(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $criteria = ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2];

        // Act
        $api = $this->api('GET', '/api/plans', $criteria);
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/', ['plan' => $criteria]);

        // Assert
        $apiFilms = array_map(
            static fn (array $programme) => array_column(array_column($programme['showtimes'], 'film'), 'slug'),
            $api['programmes'],
        );
        $webFilms = $crawler->filter('.programme')->each(
            static fn ($programme) => $programme->filter('[data-film-slug]')->each(static fn ($showtime) => $showtime->attr('data-film-slug')),
        );
        self::assertCount(3, $webFilms);
        self::assertSame($apiFilms, $webFilms);
    }
}
