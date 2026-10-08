<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DesignSystemTest extends WebTestCase
{
    public function testTheStyleGuideShowsEveryComponentAndFormStateInBothThemes(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $crawler = $client->request('GET', '/design-system');

        // Assert
        self::assertResponseIsSuccessful();
        foreach (['light', 'dark'] as $theme) {
            $preview = $crawler->filter(sprintf('.style-guide[data-theme="%s"]', $theme));
            self::assertCount(1, $preview, $theme.' preview');
            foreach (['Primary', 'Secondary', 'Danger', 'Ghost'] as $variant) {
                self::assertCount(1, $preview->selectButton($variant), $theme.': '.$variant.' button');
            }
            self::assertCount(2, $preview->filter('.logo svg'), $theme.': logo, with and without its name');
            self::assertCount(2, $preview->filter('.logo img[src$=".svg"]'), $theme.': app icon and favicon');
            self::assertCount(2, $preview->filter('.button-group[role=group] a[data-primary] svg'), $theme.': button groups, toggles off and on');
            self::assertCount(1, $preview->filter('.button-group.button-group-sm'), $theme.': compact button group');
            self::assertGreaterThan(0, $preview->filter('.button-group button[aria-pressed=true]')->count(), $theme.': pressed toggle');
            self::assertCount(2, $preview->filter('details.dropdown .dropdown-menu a'), $theme.': dropdown');
            self::assertCount(1, $preview->filter('.pagination [aria-current=page]'), $theme.': pagination');
            self::assertCount(1, $preview->filter('.flash-error[role=alert]'), $theme.': alerts');
            self::assertGreaterThanOrEqual(9, $preview->filter('.icons svg')->count(), $theme.': every icon');
            self::assertCount(1, $preview->filter('table tbody tr'), $theme.': table');
            self::assertStringContainsString('Example of an error message.', $preview->filter('form')->text());
            self::assertCount(1, $preview->filter('input.border-danger-600'), $theme.': field with an error');
            self::assertCount(1, $preview->filter('select[data-controller~="symfony--ux-autocomplete--autocomplete"]'), $theme.': autocomplete');
        }
    }
}
