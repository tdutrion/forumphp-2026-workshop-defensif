<?php

namespace App\Tests\Functional\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DesignSystemTest extends WebTestCase
{
    public function testTheStyleGuideShowsEveryComponentAndFormState(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $crawler = $client->request('GET', '/design-system');

        // Assert
        self::assertResponseIsSuccessful();
        foreach (['Primary', 'Secondary', 'Danger', 'Ghost'] as $variant) {
            self::assertCount(1, $crawler->selectButton($variant), $variant.' button');
        }
        self::assertSelectorExists('.button-group[role=group] a[data-primary] svg');
        self::assertSelectorExists('.button-group[role=group] button[aria-pressed=true]');
        self::assertSelectorExists('.flash-success');
        self::assertSelectorExists('.flash-error[role=alert]');
        self::assertSelectorExists('.flash-info');
        self::assertSelectorTextContains('form', 'Example of an error message.');
        self::assertSelectorExists('input.border-danger-600');
    }
}
