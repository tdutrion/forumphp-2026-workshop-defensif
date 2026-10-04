<?php

namespace App\Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeenFilmApiTest extends WebTestCase
{
    use DijonCatalogWithToken;

    public function testAFilmMarkedAsSeenLeavesThePlansUntilUnmarked(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $criteria = ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2];

        // Act
        $this->api('PUT', '/api/me/seen-films/f1');
        $marked = $this->client->getResponse()->getStatusCode();
        $seenFilms = $this->api('GET', '/api/me/seen-films');
        $plan = $this->api('GET', '/api/plans', $criteria);
        $this->api('DELETE', '/api/me/seen-films/f1');
        $unmarked = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertSame(204, $marked);
        self::assertSame([['slug' => 'f1', 'title' => 'Film f1']], $seenFilms);
        foreach ($plan['programmes'] as $programme) {
            self::assertNotContains('f1', array_column(array_column($programme['showtimes'], 'film'), 'slug'));
        }
        self::assertSame(204, $unmarked);
        self::assertSame([], $this->api('GET', '/api/me/seen-films'));
    }

    public function testAFilmIDoNotWantToSeeLeavesThePlansUntilRestored(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $criteria = ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2];

        // Act
        $this->api('PUT', '/api/me/unwanted-films/f3');
        $marked = $this->client->getResponse()->getStatusCode();
        $unwanted = $this->api('GET', '/api/me/unwanted-films');
        $plan = $this->api('GET', '/api/plans', $criteria);
        $this->api('DELETE', '/api/me/unwanted-films/f3');
        $restored = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertSame(204, $marked);
        self::assertSame([['slug' => 'f3', 'title' => 'Film f3']], $unwanted);
        foreach ($plan['programmes'] as $programme) {
            self::assertNotContains('f3', array_column(array_column($programme['showtimes'], 'film'), 'slug'));
        }
        self::assertSame(204, $restored);
        self::assertSame([], $this->api('GET', '/api/me/unwanted-films'));
    }

    public function testAnUnknownFilmIsAProblemWithoutTechnicalDetails(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $body = $this->api('PUT', '/api/me/seen-films/unknown-film');

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame(404, $body['status']);
        self::assertStringNotContainsString('Stack', (string) $this->client->getResponse()->getContent());
    }
}
