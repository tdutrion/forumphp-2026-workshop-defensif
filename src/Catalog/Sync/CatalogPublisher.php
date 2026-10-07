<?php

declare(strict_types=1);

namespace App\Catalog\Sync;

/**
 * Tells whoever listens that the catalog was just synchronized.
 */
interface CatalogPublisher
{
    /**
     * @param array{cities: int, cinemas: int, films: int, showtimes: int, deleted: int, errors: int, linked: int} $stats
     */
    public function publish(array $stats): void;
}
