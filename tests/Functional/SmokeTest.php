<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    public function testTheApplicationAnswersAndTheDatabaseIsReachable(): void
    {
        // Arrange
        $client = self::createClient();
        $connection = self::getContainer()->get(Connection::class);

        // Act
        $client->request('GET', '/a-page-that-does-not-exist');

        // Assert
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, (int) $connection->fetchOne('SELECT 1'));
        self::assertSame('UTC', date_default_timezone_get());
    }
}
