<?php

declare(strict_types=1);

namespace App\Api\Request;

use App\Account\SortOrder;

/**
 * The query string of GET /api/me/seen-films.
 */
final readonly class SeenFilmsQuery
{
    public function __construct(
        /** Sort by title. */
        public SortOrder $order = SortOrder::Asc,
    ) {
    }
}
