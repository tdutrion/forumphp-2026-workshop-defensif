<?php

namespace App\Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlanApiTest extends WebTestCase
{
    use DijonCatalogWithToken;

    public function testRequiresAValidToken(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $this->client->request('GET', '/api/cities');
        $withoutToken = $this->client->getResponse()->getStatusCode();
        $this->client->request('GET', '/api/cities', [], [], ['HTTP_AUTHORIZATION' => 'Bearer mm_fake']);
        $withAFakeToken = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertSame(401, $withoutToken);
        self::assertSame(401, $withAFakeToken);
    }

    public function testPlansThreeProgrammesWithTheTimesOfTheCinema(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $body = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2]);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertNull($body['reason']);
        self::assertCount(3, $body['programmes']);
        $first = $body['programmes'][0];
        self::assertSame(10, $first['wait']);
        self::assertSame(['slug' => 'f3', 'title' => 'Film f3'], $first['showtimes'][0]['film']);
        self::assertSame(['slug' => 'cinema-pathe-dijon', 'name' => 'Pathé Dijon'], $first['showtimes'][0]['cinema']);
        self::assertSame('2030-01-10T16:40:00+01:00', $first['showtimes'][0]['startsAt']);
        self::assertSame('2030-01-10T18:40:00+01:00', $first['showtimes'][0]['endsAt']);
    }

    public function testInvalidParametersGiveAProblem(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $body = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'radius' => 80]);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame('Invalid parameters', $body['title']);
        self::assertContains(['field' => 'radius', 'message' => 'The radius must be between 1 and 50 km.'], $body['errors']);
        self::assertContains(['field' => 'city', 'message' => 'Choose a city or use your position.'], $body['errors']);
    }

    public function testTheDocumentationIsPublic(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/api/doc.json');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/api/plans', (string) $client->getResponse()->getContent());
    }
}
