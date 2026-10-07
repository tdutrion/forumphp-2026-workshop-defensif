<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Sync;

use App\Catalog\Sync\NullCatalogPublisher;
use PHPUnit\Framework\TestCase;

final class NullCatalogPublisherTest extends TestCase
{
    public function testTellsNobodyAndFailsNowhere(): void
    {
        // Act
        (new NullCatalogPublisher())->publish(['cities' => 1, 'cinemas' => 1, 'films' => 1, 'showtimes' => 1, 'deleted' => 0, 'errors' => 0, 'linked' => 0]);

        // Assert
        $this->expectNotToPerformAssertions();
    }
}
