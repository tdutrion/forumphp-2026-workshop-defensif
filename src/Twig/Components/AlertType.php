<?php

declare(strict_types=1);

namespace App\Twig\Components;

/**
 * The kind of a message: it sets its colours, its role and its flash-* hook.
 */
enum AlertType: string
{
    case Success = 'success';
    case Error = 'error';
    case Info = 'info';
}
