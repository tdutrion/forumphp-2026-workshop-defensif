<?php

declare(strict_types=1);

namespace App\Twig\Components;

/**
 * A button's size.
 */
enum ButtonSize: string
{
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
}
