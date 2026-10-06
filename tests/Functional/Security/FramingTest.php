<?php

namespace App\Tests\Functional\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FramingTest extends WebTestCase
{
    public function testNoPageCanBeShownInAFrameOfAnotherSite(): void
    {
        // Arrange: whatever serves the application (Caddy, Apache, nginx...).
        $client = self::createClient();

        foreach (['/', '/login'] as $page) {
            // Act
            $client->request('GET', $page);

            // Assert
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Frame-Options', 'DENY', $page);
            self::assertResponseHeaderSame('Content-Security-Policy', "frame-ancestors 'none'", $page);
        }
    }
}
