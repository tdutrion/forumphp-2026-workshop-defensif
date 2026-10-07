<?php

declare(strict_types=1);

namespace App\Twig\Components;

/**
 * The element a button is drawn with: a link styled as a button is an a.
 */
enum ButtonTag: string
{
    case Button = 'button';
    case A = 'a';
}
