<?php

namespace App\Tests\Integration\Sdk;

use App\Sdk\Wikidata\WikidataClient;
use App\Tests\Builder\WikidataApiBuilder;
use App\Tests\Fake\FakeWikidataApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WikidataClientTest extends KernelTestCase
{
    private function client(WikidataApiBuilder $api): WikidataClient
    {
        self::bootKernel();
        self::getContainer()->get(FakeWikidataApi::class)->serve($api);

        return self::getContainer()->get(WikidataClient::class);
    }

    public function testFindsAFilmAndReadsItsFacts(): void
    {
        // Arrange
        $client = $this->client(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q182153', 'Cars', 2006, ['John Lasseter'], 'tt0317219', '920'));

        // Act
        $ids = $client->searchFilms('cars', 'fr');
        $films = $client->getFilms($ids);

        // Assert
        self::assertSame(['Q182153'], $ids);
        self::assertSame(['types' => ['Q11424'], 'years' => [2006], 'directors' => ['John Lasseter'], 'imdbId' => 'tt0317219', 'tmdbId' => '920'], $films['Q182153']);
    }

    public function testAnUnavailableWikidataGivesFalse(): void
    {
        // Arrange
        $client = $this->client(WikidataApiBuilder::aWikidataApi()->failing());

        // Act and assert
        self::assertFalse($client->searchFilms('Cars', 'fr'));
        self::assertFalse($client->getFilms(['Q182153']));
    }

    public function testAGarbledAnswerIsLikeAnError(): void
    {
        // Arrange
        $client = $this->client(WikidataApiBuilder::aWikidataApi()->answeringGarbage());

        // Act and assert
        self::assertFalse($client->searchFilms('Cars', 'fr'));
    }

    public function testMalformedIdsAreLeftOut(): void
    {
        // Arrange: ids end up in links; anything else than the expected formats is dropped.
        $client = $this->client(WikidataApiBuilder::aWikidataApi()
            ->withFilm('Q182153', 'Cars', 2006, [], 'javascript:alert(1)', '9 20'));

        // Act
        $films = $client->getFilms(['Q182153']);

        // Assert
        self::assertNull($films['Q182153']['imdbId']);
        self::assertNull($films['Q182153']['tmdbId']);
    }
}
