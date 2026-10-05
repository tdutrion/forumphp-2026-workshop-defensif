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
