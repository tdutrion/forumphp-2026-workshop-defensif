<?php

declare(strict_types=1);

namespace App\Twig\Components;

/**
 * A button's colour.
 */
enum ButtonVariant: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Danger = 'danger';
    case Ghost = 'ghost';
    case Accent = 'accent';
}
