<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * <twig:Button variant="secondary" size="sm" tag="a" href="…">: templates pass strings, the component turns them into
 * enums once; an unknown value is a mistake of the template and fails in development and in the tests.
 */
#[AsTwigComponent]
final class Button
{
    public ButtonVariant $variant = ButtonVariant::Primary;
    public ButtonSize $size = ButtonSize::Md;
    public ButtonTag $tag = ButtonTag::Button;

    public function mount(string $variant = 'primary', string $size = 'md', string $tag = 'button'): void
    {
        $this->variant = ButtonVariant::tryFrom($variant) ?? ButtonVariant::Primary;
        \assert(ButtonVariant::tryFrom($variant) instanceof ButtonVariant, \sprintf('Unknown button variant "%s".', $variant));
        $this->size = ButtonSize::tryFrom($size) ?? ButtonSize::Md;
        \assert(ButtonSize::tryFrom($size) instanceof ButtonSize, \sprintf('Unknown button size "%s".', $size));
        $this->tag = ButtonTag::tryFrom($tag) ?? ButtonTag::Button;
        \assert(ButtonTag::tryFrom($tag) instanceof ButtonTag, \sprintf('Unknown button tag "%s".', $tag));
    }
}
