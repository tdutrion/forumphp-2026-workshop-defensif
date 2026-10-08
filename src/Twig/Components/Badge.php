<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Badge
{
    public BadgeTone $tone = BadgeTone::Neutral;

    public function mount(string $tone = 'neutral'): void
    {
        $this->tone = BadgeTone::tryFrom($tone) ?? BadgeTone::Neutral;
        \assert(BadgeTone::tryFrom($tone) instanceof BadgeTone, \sprintf('Unknown badge tone "%s".', $tone));
    }
}
