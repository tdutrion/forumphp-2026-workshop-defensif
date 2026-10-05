<?php

namespace App\Tests\Functional\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LanguageSwitchTest extends WebTestCase
{
    public function testTheChosenLanguageWinsOverTheBrowserAndStaysOnThePage(): void
    {
        // Arrange: a French browser, on a public page.
        $client = self::createClient();
        $crawler = $client->request('GET', '/legal/terms', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        // Act
        $client->submit($crawler->filter('#language-switch button[value="en"]')->form());
        $redirect = $client->getResponse();
        $client->request('GET', '/legal/terms', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        // Assert
        self::assertSame(303, $redirect->getStatusCode());
        self::assertSame('/legal/terms', $redirect->headers->get('Location'));
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorTextContains('main h1', 'Terms of use');
        self::assertSelectorExists('#language-switch button[value="en"][aria-pressed="true"]');
    }

    public function testAnUnknownLanguageIsRefused(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('POST', '/locale', ['locale' => 'xx']);

        // Assert
        self::assertResponseStatusCodeSame(400);
    }
}
