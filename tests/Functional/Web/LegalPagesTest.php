<?php

namespace App\Tests\Functional\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalPagesTest extends WebTestCase
{
    public function testEveryPageLinksToTheLegalPagesWhichAnyoneCanRead(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $footer = $client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr'])->filter('footer a');
        $pages = [];
        foreach ($footer->each(static fn ($link) => $link->attr('href')) as $href) {
            $client->request('GET', $href, server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
            $pages[$href] = [$client->getResponse()->getStatusCode(), $client->getCrawler()->filter('main')->text()];
        }

        // Assert
        self::assertSame(['/legal/notice', '/legal/terms', '/legal/privacy'], array_keys($pages));
        foreach ($pages as $href => [$status]) {
            self::assertSame(200, $status, $href.' without signing in');
        }
        self::assertStringContainsString('987 542 230', $pages['/legal/notice'][1], 'the publisher');
        self::assertStringContainsString('Infomaniak', $pages['/legal/notice'][1], 'the host');
        self::assertStringContainsString('Conditions générales d\'utilisation', $pages['/legal/terms'][1]);
        self::assertStringContainsString('CNIL', $pages['/legal/privacy'][1]);
    }

    public function testTheLegalPagesExistInEnglish(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/legal/privacy', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        // Assert
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main h1', 'Privacy policy');
    }
}
