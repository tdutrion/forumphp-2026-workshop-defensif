<?php

namespace App\Tests\Functional\Web;

use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LandingPageTest extends WebTestCase
{
    use StoresEntities;

    public function testVisitorsDiscoverTheServiceAndHowToStartOrHostIt(): void
    {
        // Arrange
        $client = self::createClient();
        $this->store(CityBuilder::aCity()->build());

        // Act
        $crawler = $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Enchaînez les films');
        self::assertGreaterThan(0, $crawler->filter('main a[href="/login"]')->count(), 'a way to start');
        self::assertGreaterThan(0, $crawler->filter('main a[href$="/docs/self-hosting.md"]')->count(), 'the self-hosting guide');
        self::assertSelectorTextContains('#coverage', 'Dijon', 'the cities of the catalog');
        self::assertSelectorNotExists('form[name="plan"]', 'the planner is for signed-in users');
    }

    public function testShowsEveryChainWithAnUnlimitedPassButOnlyPatheAsSupported(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $crawler = $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        // Assert
        $supported = $crawler->filter('#chains [data-chain-supported]')->each(static fn ($chain) => $chain->text());
        $planned = $crawler->filter('#chains [data-chain-planned]')->each(static fn ($chain) => $chain->text());
        self::assertCount(1, $supported);
        self::assertStringContainsString('Pathé', $supported[0]);
        self::assertStringContainsString('France', $supported[0]);
        self::assertGreaterThan(10, \count($planned));
        self::assertStringContainsString('Cineworld', implode(' ', $planned));
        self::assertSelectorTextContains('#chains', 'Seul Pathé est pris en charge pour le moment');
    }

    public function testEveryPageCarriesTheNameAndTheLogoOfScreenRoute(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $crawler = $client->request('GET', '/legal/terms');

        // Assert
        self::assertStringEndsWith('· ScreenRoute', trim($crawler->filter('title')->text()));
        self::assertSelectorExists('header a[href="/"] svg');
        self::assertSelectorTextSame('header a[href="/"]', 'ScreenRoute');
        self::assertMatchesRegularExpression('#^/assets/images/logo-[\w-]+\.svg$#', (string) $crawler->filter('link[rel="icon"]')->attr('href'));
        self::assertStringNotContainsString('Movie Marathon', (string) $client->getResponse()->getContent());
    }

    public function testSignedInUsersGetThePlannerAtTheSameAddress(): void
    {
        // Arrange
        $client = self::createClient();
        $user = UserBuilder::aUser()->build();
        $this->store($user);
        $client->loginUser($user);

        // Act
        $client->request('GET', '/');

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="plan"]');
    }

    public function testEveryPageOffersTheSourceCodeAsTheLicenseRequires(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/legal/notice');

        // Assert
        self::assertSelectorExists('footer a[href="https://github.com/tdutrion/forumphp-2026-workshop-defensif"]');
    }
}
