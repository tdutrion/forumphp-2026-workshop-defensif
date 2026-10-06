<?php

namespace App\Tests\Functional\Web;

use App\Tests\Builder\UserBuilder;
use App\Tests\StoresEntities;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErrorPageTest extends WebTestCase
{
    use StoresEntities;

    public function testAnUnknownPageShowsATranslatedErrorPage(): void
    {
        // Arrange: without debug, as in production (no exception page).
        $client = self::createClient(['debug' => false]);
        $user = UserBuilder::aUser()->build();
        $this->store($user);
        $client->loginUser($user);
        $client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr');

        // Act
        $client->request('GET', '/films/unknown-film');

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Page introuvable');
        self::assertSelectorExists('a[href="/"]');
    }
}
