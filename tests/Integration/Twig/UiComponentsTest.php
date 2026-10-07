<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig;

use App\Twig\Components\AlertType;
use App\Twig\Components\BadgeTone;
use App\Twig\Components\ButtonSize;
use App\Twig\Components\ButtonTag;
use App\Twig\Components\ButtonVariant;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class UiComponentsTest extends KernelTestCase
{
    private function render(string $template): string
    {
        self::bootKernel();

        return self::getContainer()->get(Environment::class)->createTemplate($template)->render();
    }

    public function testEveryCaseOfEveryEnumRendersWithItsOwnClasses(): void
    {
        // Act
        $html = '';
        foreach (ButtonVariant::cases() as $variant) {
            $html .= $this->render('<twig:Button variant="'.$variant->value.'">x</twig:Button>');
        }
        foreach (ButtonSize::cases() as $size) {
            $html .= $this->render('<twig:Button size="'.$size->value.'">x</twig:Button>');
        }
        foreach (AlertType::cases() as $type) {
            $html .= $this->render('<twig:Alert type="'.$type->value.'">x</twig:Alert>');
        }
        foreach (BadgeTone::cases() as $tone) {
            $html .= $this->render('<twig:Badge tone="'.$tone->value.'">x</twig:Badge>');
        }

        // Assert
        self::assertStringContainsString('bg-danger-600', $html, 'danger button');
        self::assertStringContainsString('px-5 py-3', $html, 'large button');
        self::assertStringContainsString('flash-success', $html);
        self::assertStringContainsString('bg-accent-50', $html, 'warning badge');
    }

    public function testAButtonIsAButtonByDefaultAndALinkOnDemand(): void
    {
        // Act
        $default = $this->render('<twig:Button>x</twig:Button>');
        $link = $this->render('<twig:Button tag="'.ButtonTag::A->value.'" href="/">x</twig:Button>');

        // Assert
        self::assertStringStartsWith('<button', $default);
        self::assertStringContainsString('bg-brand-600', $default);
        self::assertStringStartsWith('<a ', $link);
    }

    public function testAnAlertThatIsAnErrorSpeaksUp(): void
    {
        // Act
        $html = $this->render('<twig:Alert type="error">x</twig:Alert>');

        // Assert
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('flash-error', $html);
    }

    #[TestWith(['<twig:Button variant="primray">x</twig:Button>', 'primray'])]
    #[TestWith(['<twig:Button size="huge">x</twig:Button>', 'huge'])]
    #[TestWith(['<twig:Button tag="div">x</twig:Button>', 'div'])]
    #[TestWith(['<twig:Alert type="warning">x</twig:Alert>', 'warning'])]
    #[TestWith(['<twig:Badge tone="pink">x</twig:Badge>', 'pink'])]
    public function testAnUnknownValueFailsInsteadOfRenderingAnUnstyledElement(string $template, string $value): void
    {
        // Assert
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('"'.$value.'"');

        // Act
        $this->render($template);
    }
}
