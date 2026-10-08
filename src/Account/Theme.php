<?php

declare(strict_types=1);

namespace App\Account;

/**
 * Colour theme of the website, chosen in the settings. Auto follows the system of the visitor.
 */
enum Theme: string
{
    case Auto = 'auto';
    case Light = 'light';
    case Dark = 'dark';
}
