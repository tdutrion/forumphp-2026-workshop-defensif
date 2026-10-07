<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Alert
{
    public AlertType $type = AlertType::Info;

    public function mount(string $type = 'info'): void
    {
        $this->type = AlertType::tryFrom($type) ?? AlertType::Info;
        \assert(AlertType::tryFrom($type) instanceof AlertType, \sprintf('Unknown alert type "%s".', $type));
    }
}
