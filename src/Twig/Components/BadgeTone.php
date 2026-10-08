<?php

declare(strict_types=1);

namespace App\Twig\Components;

/**
 * The colour of a badge.
 */
enum BadgeTone: string
{
    case Neutral = 'neutral';
    case Brand = 'brand';
    case Warning = 'warning';
}
