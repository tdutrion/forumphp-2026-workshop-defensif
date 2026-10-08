<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Catalog\Entity\Cinema;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
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

    public function testTheSeedOfTheDrawComesBackToAskForTheSameProgrammes(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $criteria = ['date' => '2030-01-10', 'city' => 'dijon'];

        // Act
        $drawn = $this->api('GET', '/api/plans', $criteria);
        $again = $this->api('GET', '/api/plans', $criteria + ['seed' => $drawn['seed']]);
        $this->api('GET', '/api/plans', $criteria + ['seed' => '-1']);
        $invalidSeed = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertIsInt($drawn['seed']);
        self::assertSame($drawn['programmes'], $again['programmes']);
        self::assertSame($drawn['seed'], $again['seed']);
        self::assertSame(422, $invalidSeed);
    }

    public function testAcceptAdsSetToZeroOrFalseMeansNo(): void
    {
        // Arrange: f5 starts at 18:30, when f2 ends; it only chains when arriving during the ads is accepted.
        $this->arrangeDijonCatalogWithToken();
        $film = FilmBuilder::aFilm()->withSlug('f5')->titled('Film f5')->lasting(100)->build();
        $cinema = self::getContainer()->get(EntityManagerInterface::class)->find(Cinema::class, 'cinema-pathe-dijon');
        $this->store($film, ShowtimeBuilder::aShowtime()->of($film)->at($cinema)->startingAt('2030-01-10 18:30:00')->build());
        // A fixed draw: the responses are compared whole, seed included.
        $criteria = ['date' => '2030-01-10', 'city' => 'dijon', 'films' => 2, 'seed' => '7'];

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

    public function testAnEmptyParameterMeansNotGiven(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $criteria = ['date' => '2030-01-10', 'city' => 'dijon', 'seed' => '7'];

        // Act
        $without = $this->api('GET', '/api/plans', $criteria);
        $withEmpty = $this->api('GET', '/api/plans', ['from' => '', 'until' => '', 'films' => '', 'position' => '', 'version' => '', 'travelMode' => '', 'acceptAds' => ''] + $criteria);
        $status = $this->client->getResponse()->getStatusCode();
        // The draw itself is only fixed by the seed: an empty seed draws again, like a missing one.
        $this->api('GET', '/api/plans', ['seed' => ''] + $criteria);
        $emptySeed = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertSame(200, $status);
        self::assertSame($without, $withEmpty);
        self::assertSame(200, $emptySeed);
    }

    public function testInvalidParametersGiveAProblem(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $body = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'films' => 9]);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame('Invalid parameters', $body['title']);
        self::assertContains(['field' => 'films', 'message' => 'Choose between 1 and 8 films.'], $body['errors']);
        self::assertContains(['field' => 'city', 'message' => 'Choose a city or use your position.'], $body['errors']);
    }

    public function testADayWithoutBookableShowtimesAndAnUnknownParameterAreInvalid(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();

        // Act
        $anotherDay = $this->api('GET', '/api/plans', ['date' => '2030-01-11', 'city' => 'dijon']);
        $anotherDayStatus = $this->client->getResponse()->getStatusCode();
        $radius = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'city' => 'dijon', 'radius' => 50]);
        $radiusStatus = $this->client->getResponse()->getStatusCode();

        // Assert
        self::assertSame(422, $anotherDayStatus);
        self::assertSame(['date'], array_column($anotherDay['errors'], 'field'));
        self::assertSame(422, $radiusStatus);
        self::assertSame(['radius'], array_column($radius['errors'], 'field'), 'the radius is fixed (10 km)');
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

    public function testACityWithoutAnOpenCinemaIsInvalidInput(): void
    {
        // Arrange: Beaune's only cinema has closed.
        $this->arrangeDijonCatalogWithToken();
        $beaune = CityBuilder::aCity()->withSlug('beaune')->named('Beaune')->build();
        $this->store($beaune, CinemaBuilder::aCinema()->withSlug('cinema-beaune')->in($beaune)->at(47.02, 4.84)->closed()->build());

        // Act
        $body = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'city' => 'beaune']);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame('city', $body['errors'][0]['field'] ?? null);
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

    public function testErrorsSpeakTheLanguageOfTheClient(): void
    {
        // Arrange
        $this->arrangeDijonCatalogWithToken();
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr');

        // Act
        $invalid = $this->api('GET', '/api/plans', ['date' => '2030-01-10', 'films' => 9]);
        $notFound = $this->api('PUT', '/api/me/seen-films/unknown-film');
        $this->client->request('GET', '/api/cities', [], [], ['HTTP_AUTHORIZATION' => 'Bearer mm_fake']);
        $rejected = json_decode((string) $this->client->getResponse()->getContent(), true);

        // Assert
        self::assertSame('Paramètres invalides', $invalid['title']);
        self::assertSame('Introuvable', $notFound['title']);
        self::assertSame('Film inconnu.', $notFound['detail']);
        self::assertSame('Non authentifié', $rejected['title']);
        self::assertSame('Jeton invalide, expiré ou révoqué.', $rejected['detail']);
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
