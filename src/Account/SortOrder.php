<?php

declare(strict_types=1);

namespace App\Account;

/**
 * The order of a list in the URL (?order=asc|desc). \SortDirection, which the repositories speak, is a pure
 * enum with no value to read from a URL: this one is the boundary.
 */
enum SortOrder: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function direction(): \SortDirection
    {
        return match ($this) {
            self::Asc => \SortDirection::Ascending,
            self::Desc => \SortDirection::Descending,
        };
    }
}
