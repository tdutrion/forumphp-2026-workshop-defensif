<?php

namespace App\Tests\Functional\Api;

use App\Catalog\Entity\Cinema;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use Doctrine\ORM\EntityManagerInterface;
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
        self::assertSame(2, $body['films']);
        self::assertCount(3, $body['programmes']);
        $first = $body['programmes'][0];
        self::assertSame(10, $first['wait']);
        self::assertSame(20, $first['breakMinutes']);
        self::assertSame(0, $first['travelMinutes']);
        self::assertSame(['slug' => 'f3', 'title' => 'Film f3'], $first['showtimes'][0]['film']);
        self::assertSame(['slug' => 'cinema-pathe-dijon', 'name' => 'Pathé Dijon'], $first['showtimes'][0]['cinema']);
        self::assertSame('2030-01-10T16:40:00+01:00', $first['showtimes'][0]['startsAt']);
        self::assertSame('2030-01-10T18:40:00+01:00', $first['showtimes'][0]['endsAt']);
        self::assertSame(20, $first['showtimes'][1]['breakMinutes']);
        self::assertSame(0, $first['showtimes'][1]['travelMinutes']);
    }

    public function testAcceptAdsSetToZeroOrFalseMeansNo(): void
    {
        // Arrange: f5 starts at 18:30, when f2 ends; it only chains when arriving during the ads is accepted.
        $this->arrangeDijonCatalogWithToken();
        $film = FilmBuilder::aFilm()->withSlug('f5')->titled('Film f5')->lasting(100)->build();
        $cinema = self::getContainer()->get(EntityManagerInterface::class)->find(Cinema::class, 'cinema-pathe-dijon');
        $this->store($film, ShowtimeBuilder::aShowtime()->of($film)->at($cinema)->startingAt('2030-01-10 18:30:00')->build());
        $criteria = ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2];

        // Act
        $withAds = $this->api('GET', '/api/plans', $criteria + ['acceptAds' => '1']);
        $withZero = $this->api('GET', '/api/plans', $criteria + ['acceptAds' => '0']);
        $withFalse = $this->api('GET', '/api/plans', $criteria + ['acceptAds' => 'false']);
        $without = $this->api('GET', '/api/plans', $criteria);

        // Assert
        self::assertNotSame($without, $withAds, 'the ads option changes the programmes');
        self::assertSame($without, $withZero);
        self::assertSame($without, $withFalse);
    }

    public function testTheTimeRangeFiltersTheProgrammes(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $body = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2, 'from' => '16:00']);
        $invalid = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'city' => 'dijon', 'from' => '25:99']);
        $reversed = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'city' => 'dijon', 'from' => '20:00', 'until' => '19:00']);

        // Assert
        foreach ($body['programmes'] as $programme) {
            self::assertNotContains('f1', array_column(array_column($programme['showtimes'], 'film'), 'slug'));
        }
        self::assertContains('from', array_column($invalid['errors'] ?? [], 'field'));
        self::assertContains('until', array_column($reversed['errors'] ?? [], 'field'));
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

    public function testErrorsAreProblemsWithTheirHttpHeaders(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $this->client->request('GET', '/api/cities', [], [], ['HTTP_AUTHORIZATION' => 'Bearer mm_fake']);
        $rejectedToken = $this->client->getResponse();
        $this->api('POST', '/api/cities');
        $wrongMethod = $this->client->getResponse();

        // Assert
        self::assertSame(401, $rejectedToken->getStatusCode());
        self::assertSame('application/problem+json', $rejectedToken->headers->get('Content-Type'));
        self::assertSame(401, json_decode((string) $rejectedToken->getContent(), true)['status'] ?? null);
        self::assertSame(405, $wrongMethod->getStatusCode());
        self::assertSame('application/problem+json', $wrongMethod->headers->get('Content-Type'));
        self::assertSame('GET', $wrongMethod->headers->get('Allow'));
    }

    public function testAnUnreadablePositionIsInvalidInput(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $body = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'position' => 'nonsense']);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame('position', $body['errors'][0]['field'] ?? null);
    }

    public function testAnUnknownTravelModeIsInvalid(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $body = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'city' => 'dijon', 'travelMode' => 'teleport']);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertContains('travelMode', array_column($body['errors'], 'field'));
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
